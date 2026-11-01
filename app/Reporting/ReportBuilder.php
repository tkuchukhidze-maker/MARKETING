<?php

namespace App\Reporting;

use App\Models\DataSource;
use App\Models\KpiTarget;
use App\Models\MarketingCost;
use App\Models\MetricDaily;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns the unified base into the CMO report: totals per brand, KPIs for the
 * period and the previous one, channels, campaigns, daily trend and targets.
 */
class ReportBuilder
{
    public const PRESETS = [
        'this_month' => 'ეს თვე',
        'last_month' => 'წინა თვე',
        '7d' => 'ბოლო 7 დღე',
        '30d' => 'ბოლო 30 დღე',
        '90d' => 'ბოლო 90 დღე',
        'ytd' => 'წლის დასაწყისიდან',
        'custom' => 'არჩეული პერიოდი',
    ];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable, 4: string} from, to, prevFrom, prevTo, preset */
    public static function period(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();
        $preset = array_key_exists((string) $preset, self::PRESETS) ? $preset : 'this_month';

        if ($preset === 'custom') {
            try {
                $f = CarbonImmutable::parse((string) $from)->startOfDay();
                $t = CarbonImmutable::parse((string) $to)->startOfDay();
            } catch (\Throwable) {
                $preset = 'this_month';
            }
            if (isset($f, $t) && $f->gt($t)) {
                [$f, $t] = [$t, $f];
            }
            if (isset($f, $t) && $f->diffInDays($t) > 731) {
                $f = $t->subDays(731);
            }
        }

        [$f, $t] = match ($preset) {
            'this_month' => [$today->startOfMonth(), max($today->startOfMonth(), $yesterday)],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            '7d' => [$yesterday->subDays(6), $yesterday],
            '30d' => [$yesterday->subDays(29), $yesterday],
            '90d' => [$yesterday->subDays(89), $yesterday],
            'ytd' => [$today->startOfYear(), max($today->startOfYear(), $yesterday)],
            'custom' => [$f, $t],
        };

        $length = (int) $f->diffInDays($t) + 1;
        if (in_array($preset, ['this_month', 'last_month'], true)) {
            // month vs the same days of the month before
            $pf = $f->subMonthNoOverflow()->startOfMonth();
            $pt = min($pf->addDays($length - 1), $pf->endOfMonth()->startOfDay());
        } elseif ($preset === 'ytd') {
            $pf = $f->subYear();
            $pt = $t->subYear();
        } else {
            $pt = $f->subDay();
            $pf = $pt->subDays($length - 1);
        }

        return [$f, $t, $pf, $pt, $preset];
    }

    /** @param Collection<int, Venue> $venues */
    public function build(Collection $venues, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo): array
    {
        $ids = $venues->pluck('id')->all();
        $current = $this->buckets($venues, $from, $to);
        $previous = $this->buckets($venues, $prevFrom, $prevTo);

        $now = Kpi::compute(array_values($current), array_values($previous));
        $prevPrevFrom = $prevFrom->subDays((int) $prevFrom->diffInDays($prevTo) + 1);
        $before = Kpi::compute(array_values($previous), array_values($this->buckets($venues, $prevPrevFrom, $prevFrom->subDay())));

        $kpis = [];
        foreach (Kpi::DEFS as $key => [$label, $group, $unit, $better, $formula]) {
            $value = $now['values'][$key] ?? null;
            $prev = $before['values'][$key] ?? null;
            $delta = Kpi::delta($key, $value, $prev);
            $kpis[$key] = compact('key', 'label', 'group', 'unit', 'formula', 'value', 'prev', 'delta') + ['tone' => Kpi::tone($key, $delta)];
        }

        // per brand, for the comparison table
        $brands = $venues->map(function (Venue $v) use ($current, $previous) {
            $k = Kpi::compute([$current[$v->id]], [$previous[$v->id]])['values'];

            return ['venue' => $v, 'kpi' => $k];
        })->values();

        return [
            'kpis' => $kpis,
            'attribution' => $now['attribution'],
            'brands' => $brands,
            'channels' => $this->channels($ids, $from, $to, $prevFrom, $prevTo),
            'campaigns' => $this->campaigns($ids, $from, $to),
            'daily' => $this->daily($ids, $from, $to),
            'costsByCategory' => MarketingCost::whereIn('venue_id', $ids)->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('category, sum(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category'),
            'targets' => $this->targets($venues, $from, $to, $kpis),
            'sources' => DataSource::whereIn('venue_id', $ids)->with('venue')->orderBy('venue_id')->orderBy('provider')->get(),
            'lastDataDay' => MetricDaily::whereIn('venue_id', $ids)->max('date'),
        ];
    }

    /** @return array<int, array{t: array<string, float>, costs: float, margin: float, share: float}> keyed by venue id */
    public function buckets(Collection $venues, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ids = $venues->pluck('id')->all();
        $range = [$from->toDateString(), $to->toDateString()];

        $sums = MetricDaily::whereIn('venue_id', $ids)->whereBetween('date', $range)
            ->whereNotIn('metric', Catalog::STOCK_METRICS)
            ->selectRaw('venue_id, provider, metric, sum(value) as total')
            ->groupBy('venue_id', 'provider', 'metric')->get();

        // snapshot metrics: the latest value of each account inside the period
        $stocks = MetricDaily::whereIn('venue_id', $ids)->whereBetween('date', $range)
            ->whereIn('metric', Catalog::STOCK_METRICS)
            ->orderBy('date')->get(['venue_id', 'provider', 'source_key', 'metric', 'value'])
            ->groupBy(fn ($r) => $r->source_key.'|'.$r->metric)
            ->map(fn ($rows) => $rows->last());

        $costs = MarketingCost::whereIn('venue_id', $ids)->whereBetween('spent_on', $range)
            ->selectRaw('venue_id, sum(amount) as total')->groupBy('venue_id')->pluck('total', 'venue_id');

        $buckets = [];
        foreach ($venues as $v) {
            $t = [];
            foreach ($sums->where('venue_id', $v->id) as $r) {
                $t[$r->provider.'.'.$r->metric] = (float) $r->total;
            }
            foreach ($stocks->where('venue_id', $v->id) as $r) {
                $k = $r->provider.'.'.$r->metric;
                $t[$k] = ($t[$k] ?? 0) + (float) $r->value;
            }
            $buckets[$v->id] = [
                't' => $t,
                'costs' => (float) ($costs[$v->id] ?? 0),
                'margin' => (float) $v->gross_margin,
                'share' => (float) $v->attribution_share,
            ];
        }

        return $buckets;
    }

    private function channels(array $ids, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $pf, CarbonImmutable $pt): array
    {
        $sum = fn ($a, $b) => MetricDaily::whereIn('venue_id', $ids)->whereBetween('date', [$a->toDateString(), $b->toDateString()])
            ->whereNotIn('metric', Catalog::STOCK_METRICS)
            ->selectRaw('provider, metric, sum(value) as total')->groupBy('provider', 'metric')->get()
            ->groupBy('provider')->map(fn ($rows) => $rows->pluck('total', 'metric')->map(fn ($v) => (float) $v));

        $cur = $sum($from, $to);
        $prev = $sum($pf, $pt);
        $totalSpend = $cur->sum(fn ($m) => $m['spend'] ?? 0);

        $rows = [];
        foreach (Catalog::PROVIDERS as $provider => $def) {
            if (! isset($cur[$provider]) && ! isset($prev[$provider])) {
                continue;
            }
            $m = $cur[$provider] ?? collect();
            $p = $prev[$provider] ?? collect();
            $rows[] = [
                'provider' => $provider,
                'label' => $def['label'],
                'color' => $def['color'],
                'channel' => $def['channel'],
                'm' => $m,
                'prev' => $p,
                'spend_share' => $totalSpend > 0 ? ($m['spend'] ?? 0) / $totalSpend * 100 : null,
            ];
        }

        return $rows;
    }

    private function campaigns(array $ids, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return MetricDaily::whereIn('venue_id', $ids)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('campaign', '!=', '')->whereIn('metric', ['spend', 'impressions', 'clicks', 'conversions', 'conversion_value'])
            ->selectRaw('venue_id, provider, campaign, metric, sum(value) as total')
            ->groupBy('venue_id', 'provider', 'campaign', 'metric')->get()
            ->groupBy(fn ($r) => $r->venue_id.'|'.$r->provider.'|'.$r->campaign)
            ->map(function ($rows) {
                $first = $rows->first();
                $m = $rows->pluck('total', 'metric')->map(fn ($v) => (float) $v);

                return [
                    'venue_id' => $first->venue_id,
                    'provider' => $first->provider,
                    'campaign' => $first->campaign,
                    'spend' => $m['spend'] ?? 0,
                    'clicks' => $m['clicks'] ?? 0,
                    'impressions' => $m['impressions'] ?? 0,
                    'conversions' => $m['conversions'] ?? 0,
                    'cpa' => ($m['conversions'] ?? 0) > 0 ? ($m['spend'] ?? 0) / $m['conversions'] : null,
                    'roas' => ($m['spend'] ?? 0) > 0 && ($m['conversion_value'] ?? 0) > 0 ? $m['conversion_value'] / $m['spend'] : null,
                ];
            })
            ->sortByDesc('spend')->take(12)->values();
    }

    /** Revenue and marketing spend per day for the trend chart. */
    private function daily(array $ids, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $metric = MetricDaily::whereIn('venue_id', $ids)->whereBetween('date', $range)
            ->whereIn('metric', ['revenue', 'spend'])->whereIn('provider', array_merge(['pos'], Catalog::providersIn('paid')))
            ->selectRaw('date, metric, sum(value) as total')->groupBy('date', 'metric')->get();
        $costs = MarketingCost::whereIn('venue_id', $ids)->whereBetween('spent_on', $range)
            ->selectRaw('spent_on, sum(amount) as total')->groupBy('spent_on')->get()
            ->mapWithKeys(fn ($r) => [CarbonImmutable::parse($r->spent_on)->toDateString() => (float) $r->total]);

        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $rows = $metric->filter(fn ($r) => CarbonImmutable::parse($r->date)->toDateString() === $key);
            $days[] = [
                'date' => $d,
                'revenue' => (float) $rows->where('metric', 'revenue')->sum('total'),
                'spend' => (float) $rows->where('metric', 'spend')->sum('total') + ($costs[$key] ?? 0),
            ];
        }

        // long periods: one point per week so the chart stays readable
        if (count($days) > 62) {
            $days = collect($days)->groupBy(fn ($d) => $d['date']->startOfWeek()->toDateString())
                ->map(fn ($w) => ['date' => $w->first()['date'], 'revenue' => $w->sum('revenue'), 'spend' => $w->sum('spend')])
                ->values()->all();
        }

        return $days;
    }

    /** Plan vs actual, only when the period sits inside one calendar month. */
    private function targets(Collection $venues, CarbonImmutable $from, CarbonImmutable $to, array $kpis): ?array
    {
        if ($from->format('Y-m') !== $to->format('Y-m')) {
            return null;
        }

        $month = $from->startOfMonth();
        $targets = KpiTarget::whereIn('venue_id', $venues->pluck('id'))->whereDate('month', $month->toDateString())->get();
        if ($targets->isEmpty()) {
            return ['month' => $month, 'rows' => []];
        }

        $daysInMonth = $month->daysInMonth;
        $elapsed = (int) $month->diffInDays($to) + 1;
        $rows = [];
        foreach ($targets->groupBy('metric') as $metric => $group) {
            if (! isset(Catalog::TARGETABLE[$metric]) || ! isset($kpis[$metric])) {
                continue;
            }
            $isRate = $kpis[$metric]['unit'] === 'pct';
            if ($isRate && $group->count() > 1) {
                continue;   // a % target can't be added up across brands
            }
            $target = $isRate ? (float) $group->first()->target : (float) $group->sum('target');
            $actual = $kpis[$metric]['value'];
            // project the month end from the pace so far
            $projected = ($actual !== null && ! $isRate && $elapsed < $daysInMonth) ? $actual / $elapsed * $daysInMonth : $actual;
            $lowerIsBetter = $metric === 'marketing_spend';
            $rows[] = [
                'metric' => $metric,
                'label' => Catalog::TARGETABLE[$metric],
                'target' => $target,
                'actual' => $actual,
                'projected' => $projected,
                'progress' => $target > 0 && $actual !== null ? $actual / $target * 100 : null,
                'on_track' => $projected === null ? null : ($lowerIsBetter ? $projected <= $target : $projected >= $target),
            ];
        }

        return ['month' => $month, 'rows' => $rows];
    }
}
