<?php

namespace App\Models;

use App\Support\Studio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'user_id', 'action', 'meta'])]
class Activity extends Model
{
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human readable Georgian description. */
    public function describe(): string
    {
        $meta = $this->meta ?? [];

        return match ($this->action) {
            'created' => 'შექმნა დავალება',
            'status' => 'სტატუსი: '.Studio::statusLabel($meta['from'] ?? '').' → '.Studio::statusLabel($meta['to'] ?? ''),
            'approved' => 'დაადასტურა',
            'revision' => 'მოითხოვა შესწორება',
            'asset' => 'ატვირთა ვიზუალი v'.($meta['version'] ?? '?'),
            'assigned' => 'შემსრულებლები: '.($meta['to'] ?? '—'),
            'started' => 'დაიწყო მუშაობა',
            'ready' => 'მონიშნა „მზადაა“',
            'updated' => 'განაახლა დეტალები',
            default => $this->action,
        };
    }
}
