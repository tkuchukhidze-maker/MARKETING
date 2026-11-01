<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use App\Reporting\Sync;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncReports extends Command
{
    protected $signature = 'reports:sync
        {--days= : How many recent days to re-fetch (default from config)}
        {--from= : Start date Y-m-d (backfill)}
        {--to= : End date Y-m-d}
        {--source= : Only this data source id}';

    protected $description = 'Pull every connected platform into the unified CMO reporting base';

    public function handle(Sync $sync): int
    {
        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : CarbonImmutable::yesterday();
        $from = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))
            : $to->subDays(max(1, (int) ($this->option('days') ?: config('reporting.sync_days'))) - 1);

        $sources = DataSource::where('is_active', true)
            ->when($this->option('source'), fn ($q, $id) => $q->whereKey($id))
            ->with('venue')->get();

        $failed = 0;
        foreach ($sources as $source) {
            $result = $sync->run($source, $from, $to);
            $failed += $result['status'] === 'error' ? 1 : 0;
            $this->line(sprintf('%-18s %-28s %-7s %5d rows %s',
                $source->venue->name, $source->label, $result['status'], $result['rows'], $result['message'] ?? ''));
        }

        $this->info("{$sources->count()} sources, {$from->toDateString()} → {$to->toDateString()}, {$failed} failed.");

        return self::SUCCESS;
    }
}
