<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One checklist item of an analysis. */
#[Fillable(['analysis_report_id', 'group', 'text', 'sort', 'done_at', 'done_by'])]
class ReportAction extends Model
{
    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AnalysisReport::class, 'analysis_report_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
