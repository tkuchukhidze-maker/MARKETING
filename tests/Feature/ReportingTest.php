<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\KpiTarget;
use App\Models\MarketingCost;
use App\Models\MetricDaily;
use App\Models\User;
use App\Models\Venue;
use App\Reporting\MetricStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private Venue $batumi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->batumi = Venue::where('slug', 'batumi')->firstOrFail();
        Carbon::setTestNow('2026-10-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function source(string $provider, array $attrs = []): DataSource
    {
        return DataSource::create([
            'venue_id' => $this->batumi->id,
            'provider' => $provider,
            'label' => $provider,
            ...$attrs,
        ]);
    }

    public function test_only_admin_and_marketing_lead_see_reports(): void
    {
        foreach (['smm', 'designer', 'copywriter', 'photographer'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('reports.index'))->assertForbidden();
        }

        $this->actingAs(User::factory()->role('manager')->create())->get(route('reports.index'))->assertOk()->assertSee('CMO რეპორტინგი');
        $this->actingAs($this->admin())->get(route('reports.index'))->assertOk()->assertSee('Magnolia Hotel');
        $this->actingAs($this->admin())->get(route('reports.sources'))->assertOk();
        $this->actingAs($this->admin())->get(route('reports.plan'))->assertOk();
    }

    public function test_smm_does_not_see_the_reporting_menu(): void
    {
        $this->actingAs(User::factory()->role('smm')->create())
            ->get(route('board', $this->batumi))->assertOk()->assertDontSee('CMO რეპორტინგი');
    }

    public function test_magnolia_is_reporting_only(): void
    {
        $magnolia = Venue::where('slug', 'magnolia')->firstOrFail();
        $this->assertFalse($magnolia->in_studio);

        $this->actingAs($this->admin())->get(route('board', $magnolia))->assertNotFound();
        $this->actingAs($this->admin())->get(route('board', $this->batumi))->assertOk()->assertDontSee('Magnolia Hotel');
    }

    public function test_pos_pushes_daily_sales_to_its_secret_url(): void
    {
        $pos = $this->source('pos');
        $this->assertNotEmpty($pos->ingest_token);

        $this->postJson(route('reporting.ingest', $pos->ingest_token), [
            'days' => [
                ['date' => '2026-10-01', 'revenue' => 4000, 'checks' => 40, 'unknown' => 5],
                ['date' => '2026-10-02', 'revenue' => 6000, 'checks' => 50],
            ],
        ])->assertOk()->assertJson(['saved' => 4]);

        // re-sending a day replaces it, never doubles it
        $this->postJson(route('reporting.ingest', $pos->ingest_token), ['date' => '2026-10-02', 'revenue' => 6500])->assertOk();

        $this->assertSame(10500.0, (float) MetricDaily::where('metric', 'revenue')->sum('value'));
        $this->postJson(route('reporting.ingest', str_repeat('x', 48)), ['date' => '2026-10-02', 'revenue' => 1])->assertNotFound();
    }

    public function test_csv_import_fills_a_source(): void
    {
        $ga = $this->source('ga4');
        $csv = "date;sessions;key_events;revenue\n01.10.2026;1 200;30;99\n2026-10-02;800;20;\n";

        $this->actingAs($this->admin())
            ->post(route('reports.sources.import', $ga), ['file' => UploadedFile::fake()->createWithContent('ga.csv', $csv)])
            ->assertSessionHas('ok');

        $this->assertSame(2000.0, (float) MetricDaily::where('metric', 'sessions')->sum('value'));
        // revenue is not a GA4 metric and must not leak in
        $this->assertSame(0, MetricDaily::where('metric', 'revenue')->count());
    }

    public function test_meta_ads_sync_converts_currency_and_counts_conversions(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [[
                'date_start' => '2026-10-18', 'campaign_name' => 'ხინკლის შუადღე',
                'spend' => '10.00', 'impressions' => '5000', 'reach' => '3000', 'clicks' => '100',
                'actions' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '7'], ['action_type' => 'link_click', 'value' => '90']],
            ]]]),
        ]);
        $ads = $this->source('meta_ads', ['account_id' => '123', 'credentials' => ['access_token' => 'tok'], 'currency_rate' => 2.7]);

        $this->artisan('reports:sync', ['--days' => 3])->assertSuccessful();

        $ads->refresh();
        $this->assertSame('ok', $ads->last_status);
        $this->assertEqualsWithDelta(27.0, MetricDaily::where('metric', 'spend')->value('value'), 0.001);
        $this->assertSame(7.0, (float) MetricDaily::where('metric', 'conversions')->value('value'));
        $this->assertSame('ხინკლის შუადღე', MetricDaily::where('metric', 'spend')->value('campaign'));
        $this->assertSame('paid', MetricDaily::where('metric', 'spend')->value('channel'));
    }

    public function test_failed_sync_is_recorded_not_thrown(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);
        $ads = $this->source('meta_ads', ['account_id' => '123', 'credentials' => ['access_token' => 'bad']]);

        $this->actingAs($this->admin())->post(route('reports.sources.sync', $ads), ['days' => 7])->assertSessionHas('ok');

        $ads->refresh();
        $this->assertSame('error', $ads->last_status);
        $this->assertStringContainsString('Invalid OAuth access token', $ads->last_error);
    }

    public function test_ga4_sync_maps_rows(): void
    {
        config(['reporting.google.client_id' => 'id', 'reporting.google.client_secret' => 'secret']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at', 'expires_in' => 3599]),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [
                ['dimensionValues' => [['value' => '20261018']], 'metricValues' => [['value' => '120'], ['value' => '100'], ['value' => '80'], ['value' => '90'], ['value' => '6']]],
            ]]),
        ]);
        $this->source('ga4', ['account_id' => '987654', 'credentials' => ['refresh_token' => 'rt']]);

        $this->artisan('reports:sync')->assertSuccessful();

        $this->assertSame(120.0, (float) MetricDaily::where('metric', 'sessions')->value('value'));
        $this->assertSame(6.0, (float) MetricDaily::where('metric', 'key_events')->value('value'));
    }

    public function test_dashboard_shows_romi_from_unified_base(): void
    {
        $store = app(MetricStore::class);
        $pos = $this->source('pos');
        $ads = $this->source('meta_ads');
        $store->save($pos, [['date' => '2026-10-05', 'metric' => 'revenue', 'value' => 100000], ['date' => '2026-10-05', 'metric' => 'new_customers', 'value' => 500]]);
        $store->save($ads, [['date' => '2026-10-05', 'metric' => 'spend', 'value' => 3000]]);
        MarketingCost::create(['venue_id' => $this->batumi->id, 'spent_on' => '2026-10-06', 'category' => 'influencer', 'amount' => 2000]);
        // margin 65%, attribution 30% → (30 000 × 0.65 − 5 000) ÷ 5 000 = 290%
        $this->batumi->update(['gross_margin' => 0.65, 'attribution_share' => 0.30]);

        $this->actingAs($this->admin())
            ->get(route('reports.index', ['brand' => 'batumi', 'period' => 'this_month']))
            ->assertOk()
            ->assertSee('290%')
            ->assertSee('5 000 ₾')
            ->assertSee('100 000 ₾')
            ->assertSee('10.00 ₾');   // CAC = 5 000 ÷ 500
    }

    public function test_costs_targets_and_brand_settings(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('reports.costs.store'), [
            'venue_id' => $this->batumi->id, 'spent_on' => '2026-10-10', 'category' => 'agency', 'amount' => 1500, 'note' => 'SMM სააგენტო',
        ])->assertSessionHas('ok');
        $this->assertSame(1, MarketingCost::count());

        $this->post(route('reports.targets.save'), [
            'month' => '2026-10',
            'targets' => [$this->batumi->id => ['revenue' => '200000', 'romi' => '150', 'bogus' => '1']],
        ])->assertSessionHas('ok');
        $this->assertSame(2, KpiTarget::count());

        $this->put(route('reports.brands.save', $this->batumi), ['gross_margin' => 60, 'attribution_share' => 25])->assertSessionHas('ok');
        $this->assertEqualsWithDelta(0.60, $this->batumi->fresh()->gross_margin, 0.0001);

        $this->get(route('reports.index', ['brand' => 'batumi']))->assertOk()->assertSee('გეგმა და ფაქტი')->assertSee('შემოსავალი');
    }

    public function test_export_is_a_csv(): void
    {
        $response = $this->actingAs($this->admin())->get(route('reports.export', ['period' => 'last_month']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ROMI', $response->streamedContent());
    }

    public function test_source_credentials_are_encrypted_and_kept_on_blank_update(): void
    {
        $this->actingAs($this->admin())->post(route('reports.sources.store'), [
            'venue_id' => $this->batumi->id, 'provider' => 'instagram', 'label' => 'IG', 'account_id' => '1789',
            'credentials' => ['access_token' => 'secret-token'],
        ])->assertSessionHas('ok');

        $source = DataSource::firstOrFail();
        $this->assertStringNotContainsString('secret-token', (string) $source->getRawOriginal('credentials'));
        $this->assertSame('secret-token', $source->credential('access_token'));

        $this->put(route('reports.sources.update', $source), ['label' => 'IG 2', 'account_id' => '1789', 'credentials' => ['access_token' => '']]);
        $this->assertSame('secret-token', $source->fresh()->credential('access_token'));
        $this->get(route('reports.sources'))->assertOk()->assertDontSee('secret-token');
    }
}
