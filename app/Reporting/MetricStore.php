<?php

namespace App\Reporting;

use App\Models\DataSource;
use App\Models\MetricDaily;
use Illuminate\Support\Carbon;

/**
 * Writes rows from any connector, CSV or webhook into the unified base.
 * Same date / metric / campaign arriving twice is summed first, then the
 * row replaces what was stored before (so re-syncing never double counts).
 */
class MetricStore
{
    /** @param iterable<array{date: string, metric: string, value: float, campaign?: string}> $rows */
    public function save(DataSource $source, iterable $rows): int
    {
        $rate = (float) $source->currency_rate ?: 1.0;
        $merged = [];

        foreach ($rows as $r) {
            if (! isset(Catalog::METRICS[$r['metric']])) {
                continue;
            }
            $campaign = (string) ($r['campaign'] ?? '');
            $key = $r['date'].'|'.$r['metric'].'|'.$campaign;
            $value = (float) $r['value'] * (in_array($r['metric'], Catalog::MONEY_METRICS, true) ? $rate : 1);

            if (isset($merged[$key])) {
                // a snapshot (followers) keeps one value; everything else adds up
                $merged[$key]['value'] = in_array($r['metric'], Catalog::STOCK_METRICS, true)
                    ? $value
                    : $merged[$key]['value'] + $value;
            } else {
                $merged[$key] = ['date' => $r['date'], 'metric' => $r['metric'], 'campaign' => $campaign, 'value' => $value];
            }
        }

        $now = Carbon::now();
        $records = array_map(fn ($r) => [
            'venue_id' => $source->venue_id,
            'data_source_id' => $source->id,
            'source_key' => $source->sourceKey(),
            'provider' => $source->provider,
            'channel' => $source->channel(),
            'campaign' => $r['campaign'],
            'date' => $r['date'],
            'metric' => $r['metric'],
            'value' => round($r['value'], 4),
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($merged));

        foreach (array_chunk($records, 500) as $chunk) {
            MetricDaily::upsert(
                $chunk,
                ['venue_id', 'source_key', 'campaign', 'date', 'metric'],
                ['data_source_id', 'provider', 'channel', 'value', 'updated_at'],
            );
        }

        return count($records);
    }
}
