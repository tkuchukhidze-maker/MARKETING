<?php

namespace App\Models;

use App\Support\Studio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One platform a task is made for, with that platform's format. */
#[Fillable(['task_id', 'platform', 'format', 'published_at', 'published_by', 'post_url'])]
class TaskChannel extends Model
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function label(): string
    {
        return Studio::platformLabel($this->platform).($this->format ? ' · '.$this->format : '');
    }
}
