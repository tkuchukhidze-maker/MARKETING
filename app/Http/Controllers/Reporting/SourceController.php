<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Models\Venue;
use App\Reporting\Catalog;
use App\Reporting\CsvMetrics;
use App\Reporting\MetricStore;
use App\Reporting\Sync;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Connected accounts: add, edit credentials, sync now, CSV import. */
class SourceController extends Controller
{
    public function index(): View
    {
        return view('reports.sources', [
            'venues' => Venue::orderBy('sort')->with(['dataSources' => fn ($q) => $q->orderBy('provider')])->get(),
            'googleReady' => config('reporting.google.client_id') && config('reporting.google.client_secret'),
            'adsReady' => (bool) config('reporting.google.ads_developer_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $source = DataSource::create($data);

        return redirect()->route('reports.sources')->with('ok', "„{$source->label}“ დაემატა.".($source->isPush() ? ' მონაცემების მისამართი იხილე ბარათზე.' : ' დააჭირე „სინქრონიზაციას“.'));
    }

    public function update(Request $request, DataSource $source): RedirectResponse
    {
        $data = $this->validated($request, false, $source);
        // empty secret fields keep what is stored — tokens are never shown back
        $data['credentials'] = array_merge($source->credentials ?? [], array_filter($data['credentials'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $source->update($data);

        return back()->with('ok', "„{$source->label}“ განახლდა.");
    }

    public function destroy(DataSource $source): RedirectResponse
    {
        $source->metrics()->delete();
        $source->delete();

        return back()->with('ok', "„{$source->label}“ და მისი მონაცემები წაიშალა.");
    }

    public function sync(Request $request, DataSource $source, Sync $sync): RedirectResponse
    {
        $days = min(max((int) $request->input('days', 30), 1), 365);
        $to = CarbonImmutable::yesterday();
        $result = $sync->run($source, $to->subDays($days - 1), $to);

        return back()->with('ok', match ($result['status']) {
            'ok' => "„{$source->label}“: {$result['rows']} ჩანაწერი განახლდა (ბოლო {$days} დღე).",
            'manual' => "„{$source->label}“ ავტომატურად არ იტვირთება — ატვირთე CSV ან გამოიყენე მონაცემების მისამართი.",
            default => "„{$source->label}“: შეცდომა — {$result['message']}",
        });
    }

    public function import(Request $request, DataSource $source, MetricStore $store): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,text/tab-separated-values']]);

        $parsed = CsvMetrics::parse((string) file_get_contents($request->file('file')->getRealPath()), Catalog::PROVIDERS[$source->provider]['metrics']);
        if ($parsed['rows'] === []) {
            return back()->withErrors(['file' => implode(' ', $parsed['errors']) ?: 'ფაილში მონაცემი ვერ ვიპოვე.']);
        }

        $saved = $store->save($source, $parsed['rows']);
        $source->forceFill(['last_synced_at' => now(), 'last_status' => 'ok', 'last_error' => null])->save();
        $note = $parsed['errors'] ? ' შენიშვნა: '.implode(' ', array_slice($parsed['errors'], 0, 3)) : '';

        return back()->with('ok', "„{$source->label}“: CSV-დან {$saved} ჩანაწერი შევიდა.{$note}");
    }

    private function validated(Request $request, bool $creating, ?DataSource $source = null): array
    {
        $provider = $creating ? $request->input('provider') : $source->provider;
        $fields = Catalog::PROVIDERS[$provider]['fields'] ?? [];

        $rules = [
            'label' => ['required', 'string', 'max:120'],
            'account_id' => ['nullable', 'string', 'max:255'],
            'currency_rate' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
        if ($creating) {
            $rules['venue_id'] = ['required', Rule::exists('venues', 'id')];
            $rules['provider'] = ['required', Rule::in(array_keys(Catalog::PROVIDERS))];
        }
        foreach (array_keys($fields) as $key) {
            $rules["credentials.{$key}"] = ['nullable', 'string', 'max:4000'];
        }

        $data = $request->validate($rules);
        $data['currency_rate'] = $data['currency_rate'] ?? 1;
        $data['is_active'] = $request->boolean('is_active', $creating || $source->is_active);
        $data['credentials'] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($data['credentials'] ?? [], $fields));

        return $data;
    }
}
