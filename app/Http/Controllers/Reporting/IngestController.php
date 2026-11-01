<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Reporting\Catalog;
use App\Reporting\CsvMetrics;
use App\Reporting\MetricStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push endpoint for systems we cannot pull from (POS, booking engine…).
 *
 * POST /api/reporting/ingest/{token}
 *   {"date": "2026-10-31", "revenue": 4520.5, "checks": 61, "guests": 140}
 * or many days at once:
 *   {"days": [{"date": "...", "revenue": ...}, ...]}
 * Optional "campaign" per day. Any metric key from the catalog is accepted.
 */
class IngestController extends Controller
{
    public function __invoke(Request $request, string $token, MetricStore $store): JsonResponse
    {
        $source = DataSource::where('ingest_token', $token)->where('is_active', true)->first();
        abort_if(! $source || strlen($token) < 32, 404);

        $days = $request->has('days') ? (array) $request->input('days') : [$request->all()];
        $rows = [];
        $errors = [];

        foreach (array_slice($days, 0, 400) as $i => $day) {
            $date = is_array($day) ? CsvMetrics::date((string) ($day['date'] ?? '')) : null;
            if (! $date) {
                $errors[] = "#{$i}: date (Y-m-d) აკლია ან არასწორია";

                continue;
            }
            foreach ($day as $metric => $value) {
                if (isset(Catalog::METRICS[$metric]) && is_numeric($value)) {
                    $rows[] = ['date' => $date, 'metric' => $metric, 'value' => (float) $value, 'campaign' => (string) ($day['campaign'] ?? '')];
                }
            }
        }

        $saved = $rows ? $store->save($source, $rows) : 0;
        if ($saved) {
            $source->forceFill(['last_synced_at' => now(), 'last_status' => 'ok', 'last_error' => null])->save();
        }

        return response()->json(['saved' => $saved, 'errors' => $errors], $saved || ! $errors ? 200 : 422);
    }
}
