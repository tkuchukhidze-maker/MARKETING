<?php

namespace App\Models;

use App\Support\Studio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'venue_id', 'title', 'brief', 'reference_links', 'status',
    'creator_id', 'approved_by', 'approved_at', 'due_on', 'publish_at',
])]
class Task extends Model
{
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'publish_at' => 'datetime',
            'approved_at' => 'datetime',
            'reference_links' => 'array',
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(TaskChannel::class)->orderByRaw(
            "case platform when 'facebook' then 1 when 'instagram' then 2 when 'story' then 3 else 4 end"
        );
    }

    public function hasPlatform(string $platform): bool
    {
        return $this->channels->contains('platform', $platform);
    }

    /** Only tasks made for the given platform. */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->whereHas('channels', fn ($q) => $q->where('platform', $platform));
    }

    /**
     * Replace the task's platforms with the given platform => format map,
     * keeping rows (and the visuals tied to them) for platforms that stay.
     *
     * @param  array<string, ?string>  $map
     */
    public function syncChannels(array $map): void
    {
        $this->channels()->whereNotIn('platform', array_keys($map))->delete();

        foreach ($map as $platform => $format) {
            $this->channels()->updateOrCreate(['platform' => $platform], ['format' => $format ?: null]);
        }

        $this->unsetRelation('channels');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public const MAX_ASSIGNEES = 3;

    /** The people on this task (up to three), each with their own part. */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->orderBy('id');
    }

    public function assignmentFor(?User $user): ?TaskAssignment
    {
        return $user ? $this->assignments->firstWhere('user_id', $user->id) : null;
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id));
    }

    /**
     * Replace the people on the task with the given user_id => part map,
     * keeping the progress of everyone who stays on it.
     *
     * @param  array<int, ?string>  $map
     */
    public function syncAssignments(array $map): void
    {
        $this->assignments()->whereNotIn('user_id', array_keys($map))->delete();

        foreach ($map as $userId => $note) {
            $row = $this->assignments()->firstOrNew(['user_id' => $userId]);
            $row->note = $note ?: null;
            if (! $row->exists) {
                $row->status = 'assigned';
            }
            $row->save();
        }

        $this->unsetRelation('assignments');
        $this->settleFromAssignments(null);
    }

    /** The person opens their part and starts working on it. */
    public function startWork(User $user): void
    {
        $a = $this->assignmentFor($user);
        abort_unless($a, 403, 'ეს დავალება შენზე არ არის.');

        $a->update(['status' => 'in_progress', 'started_at' => $a->started_at ?? now(), 'ready_at' => null]);
        $this->log($user, 'started');

        if (in_array($this->status, ['todo', 'review'], true)) {
            $this->moveTo('in_progress', $user);
        }
    }

    /** The person marks their part ready; when everyone is ready the task goes to review. */
    public function markReady(User $user): void
    {
        $a = $this->assignmentFor($user);
        abort_unless($a, 403, 'ეს დავალება შენზე არ არის.');

        $a->update(['status' => 'ready', 'started_at' => $a->started_at ?? now(), 'ready_at' => now()]);
        $this->log($user, 'ready');
        $this->unsetRelation('assignments');
        $this->settleFromAssignments($user);
    }

    /**
     * Keep the task's board status in step with its people:
     * everyone ready → review; someone working again → in progress.
     */
    public function settleFromAssignments(?User $by): void
    {
        $this->loadMissing('assignments');
        if ($this->assignments->isEmpty() || in_array($this->status, ['approved', 'published'], true)) {
            return;
        }

        $allReady = $this->assignments->every(fn (TaskAssignment $a) => $a->status === 'ready');

        if ($allReady && $this->status !== 'review') {
            $this->moveTo('review', $by);
        } elseif (! $allReady && $this->status === 'review') {
            $this->moveTo('in_progress', $by);
        }
    }

    /** Sent back for changes: everyone who was ready goes back to work. */
    public function reopenAssignments(): void
    {
        $this->assignments()->where('status', 'ready')->update(['status' => 'in_progress', 'ready_at' => null]);
        $this->unsetRelation('assignments');
    }

    public function readyCount(): int
    {
        return $this->assignments->where('status', 'ready')->count();
    }

    private function moveTo(string $status, ?User $by): void
    {
        $from = $this->status;
        $this->update(['status' => $status]);
        $this->log($by, 'status', ['from' => $from, 'to' => $status]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(TaskAsset::class)->orderByDesc('version');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    /** Tasks that are not finished yet. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['approved', 'published']);
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null
            && $this->due_on->isBefore(today())
            && ! in_array($this->status, ['approved', 'published'], true);
    }

    /** Which statuses this user may move the task into. */
    public function allowedStatusesFor(User $user): array
    {
        $all = array_keys(Studio::STATUSES);

        if ($user->canApprove()) {
            return $all;
        }

        // once approved, only a manager can reopen it; others may only publish it
        if ($this->status === 'approved') {
            return ['published'];
        }
        if ($this->status === 'published') {
            return [];
        }

        return array_values(array_filter(
            $all,
            fn (string $s) => ! in_array($s, Studio::APPROVAL_STATUSES, true)
                // only an approved task can be marked published by non-approvers
                && ($s !== 'published' || $this->status === 'approved'),
        ));
    }

    public function log(?User $user, string $action, array $meta = []): void
    {
        $this->activities()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'meta' => $meta ?: null,
        ]);
    }
}
