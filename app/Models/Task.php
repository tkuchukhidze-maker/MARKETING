<?php

namespace App\Models;

use App\Support\Studio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'venue_id', 'title', 'platform', 'format', 'brief', 'status',
    'assignee_id', 'creator_id', 'approved_by', 'approved_at', 'due_on', 'publish_at',
])]
class Task extends Model
{
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'publish_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
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
