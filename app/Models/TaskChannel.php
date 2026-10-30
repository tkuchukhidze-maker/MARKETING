<?php

namespace App\Models;

use App\Support\Studio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One platform a task is made for, with that platform's format. */
#[Fillable(['task_id', 'platform', 'format'])]
class TaskChannel extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function label(): string
    {
        return Studio::platformLabel($this->platform).($this->format ? ' · '.$this->format : '');
    }
}
