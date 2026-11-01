<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\KpiTarget;
use App\Models\MarketingCost;
use App\Models\Venue;
use App\Reporting\Catalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Costs no API reports, monthly targets, and each brand's margin / attribution. */
class PlanController extends Controller
{
    public function index(Request $request): View
    {
        try {
            $month = CarbonImmutable::parse((string) $request->query('month', today()->format('Y-m')).'-01')->startOfMonth();
        } catch (\Throwable) {
            $month = CarbonImmutable::today()->startOfMonth();
        }
        $venues = Venue::orderBy('sort')->get();

        return view('reports.plan', [
            'month' => $month,
            'venues' => $venues,
            'costs' => MarketingCost::with(['venue', 'user'])
                ->whereBetween('spent_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->orderByDesc('spent_on')->orderByDesc('id')->get(),
            'targets' => KpiTarget::whereDate('month', $month->toDateString())->get()
                ->mapWithKeys(fn ($t) => [$t->venue_id.'.'.$t->metric => $t->target]),
            'currency' => config('reporting.currency'),
        ]);
    }

    public function storeCost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', Rule::exists('venues', 'id')],
            'spent_on' => ['required', 'date'],
            'category' => ['required', Rule::in(array_keys(Catalog::COST_CATEGORIES))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        MarketingCost::create($data + ['user_id' => $request->user()->id, 'channel' => 'other']);

        return back()->with('ok', 'ხარჯი დაემატა.');
    }

    public function destroyCost(MarketingCost $cost): RedirectResponse
    {
        $cost->delete();

        return back()->with('ok', 'ხარჯი წაიშალა.');
    }

    public function saveTargets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'targets' => ['array'],
            'targets.*' => ['array'],
            'targets.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ]);
        $month = $data['month'].'-01';
        $venueIds = Venue::pluck('id')->all();

        foreach ($data['targets'] ?? [] as $venueId => $metrics) {
            if (! in_array((int) $venueId, $venueIds, true)) {
                continue;
            }
            foreach ($metrics as $metric => $value) {
                if (! isset(Catalog::TARGETABLE[$metric])) {
                    continue;
                }
                $keys = ['venue_id' => (int) $venueId, 'month' => $month, 'metric' => $metric];
                if ($value === null || $value === '') {
                    KpiTarget::where('venue_id', $keys['venue_id'])->whereDate('month', $month)->where('metric', $metric)->delete();
                } else {
                    KpiTarget::where('venue_id', $keys['venue_id'])->whereDate('month', $month)->where('metric', $metric)->delete();
                    KpiTarget::create($keys + ['target' => (float) $value]);
                }
            }
        }

        return back()->with('ok', 'გეგმა შენახულია.');
    }

    public function saveBrand(Request $request, Venue $venue): RedirectResponse
    {
        $data = $request->validate([
            'gross_margin' => ['required', 'numeric', 'min:1', 'max:100'],
            'attribution_share' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $venue->update([
            'gross_margin' => $data['gross_margin'] / 100,
            'attribution_share' => $data['attribution_share'] / 100,
        ]);

        return back()->with('ok', "{$venue->name}: პარამეტრები შენახულია.");
    }
}
