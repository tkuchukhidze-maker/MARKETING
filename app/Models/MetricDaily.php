<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One number from one platform for one brand on one day — the unified base. */
#[Fillable(['venue_id', 'data_source_id', 'source_key', 'provider', 'channel', 'campaign', 'date', 'metric', 'value'])]
class MetricDaily extends Model
{
    protected $table = 'metrics_daily';

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'float',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
