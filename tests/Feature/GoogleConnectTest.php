<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\MetricDaily;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['reporting.google.client_id' => 'cid', 'reporting.google.client_secret' => 'secret']);
        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt-secret', 'scope' => 'x', 'expires_in' => 3599]),
            'openidconnect.googleapis.com/*' => Http::response(['email' => 'cmo@episodegroup.ge']),
            'analyticsadmin.googleapis.com/*' => Http::response(['accountSummaries' => [[
                'displayName' => 'Batumis Sakhlshi', 'propertySummaries' => [['property' => 'properties/561100185', 'displayName' => 'batumissaklshi.ge']],
            ]]]),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
                ['siteUrl' => 'sc-domain:batumissaklshi.ge', 'permissionLevel' => 'siteOwner'],
                ['siteUrl' => 'https://other.ge/', 'permissionLevel' => 'siteUnverifiedUser'],
            ]]),
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [
                ['dimensionValues' => [['value' => '20261030']], 'metricValues' => [['value' => '50'], ['value' => '40'], ['value' => '30'], ['value' => '35'], ['value' => '4']]],
            ]]),
        ]);
    }

    public function test_connect_redirects_to_google_with_state_and_offline_access(): void
    {
        $response = $this->get(route('reports.google.connect'));

        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('analytics.readonly', urldecode($url));
        $this->assertNotEmpty(session('google_oauth_state'));
    }

    public function test_callback_stores_encrypted_refresh_token_and_lists_assets(): void
    {
        $this->fakeGoogle();
        $this->get(route('reports.google.connect'));
        $state = session('google_oauth_state');

        $this->get(route('reports.google.callback', ['code' => 'abc', 'state' => $state]))
            ->assertRedirect(route('reports.sources'))
            ->assertSessionHas('ok');

        $c = OAuthConnection::google();
        $this->assertSame('cmo@episodegroup.ge', $c->email);
        $this->assertSame('rt-secret', $c->refresh_token);
        $this->assertStringNotContainsString('rt-secret', (string) $c->getRawOriginal('refresh_token'));

        $this->get(route('reports.sources'))
            ->assertOk()
            ->assertSee('cmo@episodegroup.ge')
            ->assertSee('batumissaklshi.ge')
            ->assertSee('sc-domain:batumissaklshi.ge')
            ->assertDontSee('https://other.ge/')
            ->assertSee('Quota exceeded')
            ->assertDontSee('rt-secret');
    }

    public function test_bad_state_is_rejected(): void
    {
        $this->get(route('reports.google.connect'));
        $this->get(route('reports.google.callback', ['code' => 'abc', 'state' => 'wrong']))
            ->assertRedirect(route('reports.sources'))
            ->assertSessionHasErrors('google');
        $this->assertNull(OAuthConnection::google());
    }

    public function test_add_source_uses_shared_sign_in_and_pulls_data(): void
    {
        $this->fakeGoogle();
        OAuthConnection::create(['provider' => 'google', 'email' => 'cmo@episodegroup.ge', 'refresh_token' => 'rt-secret']);
        $batumi = Venue::where('slug', 'batumi')->firstOrFail();

        $this->post(route('reports.google.add'), [
            'venue_id' => $batumi->id, 'provider' => 'ga4', 'account_id' => '561100185', 'label' => 'GA4 — batumissaklshi.ge',
        ])->assertSessionHas('ok');

        $source = DataSource::where('provider', 'ga4')->firstOrFail();
        $this->assertSame('ok', $source->last_status);
        $this->assertSame(50.0, (float) MetricDaily::where('metric', 'sessions')->value('value'));
    }

    public function test_disconnect_revokes_and_forgets(): void
    {
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);
        OAuthConnection::create(['provider' => 'google', 'refresh_token' => 'rt']);

        $this->delete(route('reports.google.disconnect'))->assertSessionHas('ok');
        $this->assertNull(OAuthConnection::google());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'revoke'));
    }

    public function test_team_members_cannot_connect_google(): void
    {
        $this->actingAs(User::factory()->role('smm')->create())
            ->get(route('reports.google.connect'))->assertForbidden();
    }
}
