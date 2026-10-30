<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's part of a task and how far they are with it. */
#[Fillable(['task_id', 'user_id', 'note', 'status', 'started_at', 'ready_at'])]
class TaskAssignment extends Model
{
    public const STATUSES = [
        'assigned'    => ['ახალი — გასაცნობი', '#676879', '#FFFFFF'],
        'in_progress' => ['მუშავდება',         '#1F76C2', '#FFFFFF'],
        'ready'       => ['მზადაა',            '#00854D', '#FFFFFF'],
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function style(): string
    {
        [, $bg, $fg] = self::STATUSES[$this->status] ?? self::STATUSES['assigned'];

        return "background:{$bg};color:{$fg}";
    }
}
