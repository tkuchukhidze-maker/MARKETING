<?php

namespace App\Reporting;

use App\Models\DataSource;
use App\Reporting\Connectors\Registry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Pulls one source for a date range and records how it went. */
class Sync
{
    public function __construct(private MetricStore $store)
    {
    }

    /** @return array{status: string, rows: int, message: ?string} */
    public function run(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $connector = Registry::for($source->provider);

        if (! $connector->isConfigured($source)) {
            // push / CSV-only sources are not an error, just nothing to pull
            $status = $source->isPush() || ! $source->credentials ? 'manual' : 'error';
            $message = $status === 'manual' ? null : 'ავტორიზაციის მონაცემები ან ანგარიშის ID აკლია (ან Google-ის აპი .env-ში).';
            $source->forceFill(['last_status' => $status, 'last_error' => $message])->save();

            return ['status' => $status, 'rows' => 0, 'message' => $message];
        }

        try {
            $rows = $this->store->save($source, $connector->fetch($source, $from, $to));
            $source->forceFill(['last_synced_at' => now(), 'last_status' => 'ok', 'last_error' => null])->save();

            return ['status' => 'ok', 'rows' => $rows, 'message' => null];
        } catch (Throwable $e) {
            Log::warning('reporting sync failed', ['source' => $source->id, 'provider' => $source->provider, 'error' => $e->getMessage()]);
            $source->forceFill(['last_status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return ['status' => 'error', 'rows' => 0, 'message' => $e->getMessage()];
        }
    }
}
