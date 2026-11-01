<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Monthly plan for one brand and one KPI. */
#[Fillable(['venue_id', 'month', 'metric', 'target'])]
class KpiTarget extends Model
{
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'target' => 'float',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
