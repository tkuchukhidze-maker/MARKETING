<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Models\OAuthConnection;
use App\Models\Venue;
use App\Reporting\Sync;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Connect Google": one sign-in for GA4, Search Console, Business Profile and
 * Google Ads. The refresh token goes straight from Google into the database
 * (encrypted) — nobody copies it. Properties, sites and locations are then
 * listed so a source is added with one click.
 */
class GoogleController extends Controller
{
    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/business.manage',
        'https://www.googleapis.com/auth/adwords',
    ];

    public function connect(Request $request): RedirectResponse
    {
        abort_unless(config('reporting.google.client_id') && config('reporting.google.client_secret'), 404);

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('reporting.google.client_id'),
            'redirect_uri' => route('reports.google.callback'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('google_oauth_state');
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('reports.sources')->withErrors(['google' => 'Google-ის დაკავშირება ვერ დადასტურდა — სცადე თავიდან.']);
        }
        if ($request->filled('error')) {
            return redirect()->route('reports.sources')->withErrors(['google' => 'Google: '.$request->query('error')]);
        }

        $token = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'code' => (string) $request->query('code'),
            'client_id' => config('reporting.google.client_id'),
            'client_secret' => config('reporting.google.client_secret'),
            'redirect_uri' => route('reports.google.callback'),
            'grant_type' => 'authorization_code',
        ]);

        if ($token->failed() || ! $token->json('refresh_token')) {
            return redirect()->route('reports.sources')->withErrors([
                'google' => 'Google-მა refresh token არ დააბრუნა: '.($token->json('error_description') ?? $token->json('error') ?? 'უცნობი შეცდომა'),
            ]);
        }

        $email = Http::withToken($token->json('access_token'))->timeout(15)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')->json('email');

        OAuthConnection::updateOrCreate(['provider' => 'google'], [
            'email' => $email,
            'refresh_token' => $token->json('refresh_token'),
            'scopes' => (string) $token->json('scope'),
            'user_id' => $request->user()->id,
        ]);
        Cache::forget('google-discovery');

        return redirect()->route('reports.sources')->with('ok', 'Google დაკავშირდა'.($email ? " ({$email})" : '').'. ქვემოთ აირჩიე, რა დაემატოს.');
    }

    public function disconnect(): RedirectResponse
    {
        if ($c = OAuthConnection::google()) {
            Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/revoke', ['token' => $c->refresh_token]);
            $c->delete();
        }
        Cache::forget('google-discovery');

        return back()->with('ok', 'Google გაითიშა.');
    }

    /** Add a GA4 property / Search Console site / Business Profile location as a source and pull 90 days. */
    public function add(Request $request, Sync $sync): RedirectResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', Rule::exists('venues', 'id')],
            'provider' => ['required', Rule::in(['ga4', 'search_console', 'google_business', 'google_ads'])],
            'account_id' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:120'],
        ]);

        $source = DataSource::firstOrCreate(
            ['venue_id' => $data['venue_id'], 'provider' => $data['provider'], 'account_id' => $data['account_id']],
            ['label' => $data['label'], 'currency_rate' => 1, 'is_active' => true],
        );

        $to = CarbonImmutable::yesterday();
        $result = $sync->run($source, $to->subDays(89), $to);

        return back()->with('ok', "„{$source->label}“ დაემატა".match ($result['status']) {
            'ok' => " და {$result['rows']} ჩანაწერი ჩამოიტვირთა (90 დღე).",
            'error' => ", მაგრამ ჩამოტვირთვა ვერ მოხერხდა: {$result['message']}",
            default => '.',
        });
    }

    /**
     * What the signed-in Google account can see. Each API is asked separately:
     * one that is off (e.g. Business Profile without quota) doesn't hide the rest.
     *
     * @return array{ga4: list<array>, search_console: list<array>, google_business: list<array>, errors: array<string, string>}
     */
    public static function discover(): array
    {
        $connection = OAuthConnection::google();
        if (! $connection || ! config('reporting.google.client_id')) {
            return ['ga4' => [], 'search_console' => [], 'google_business' => [], 'errors' => []];
        }

        return Cache::remember('google-discovery', now()->addMinutes(10), function () use ($connection) {
            $out = ['ga4' => [], 'search_console' => [], 'google_business' => [], 'errors' => []];

            $access = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('reporting.google.client_id'),
                'client_secret' => config('reporting.google.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ])->json('access_token');
            if (! $access) {
                $out['errors']['auth'] = 'Google-ის წვდომა ვადაგასულია ან გაუქმდა — დააკავშირე თავიდან.';

                return $out;
            }
            $google = fn () => Http::withToken($access)->timeout(30)->acceptJson();
            $fail = fn ($r) => $r->json('error.message') ?? ('HTTP '.$r->status());

            $ga = $google()->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', ['pageSize' => 200]);
            if ($ga->successful()) {
                foreach ($ga->json('accountSummaries') ?? [] as $acc) {
                    foreach ($acc['propertySummaries'] ?? [] as $p) {
                        $out['ga4'][] = ['id' => Str::after($p['property'], 'properties/'), 'name' => $p['displayName'], 'group' => $acc['displayName'] ?? ''];
                    }
                }
            } else {
                $out['errors']['ga4'] = $fail($ga);
            }

            $sc = $google()->get('https://www.googleapis.com/webmasters/v3/sites');
            if ($sc->successful()) {
                foreach ($sc->json('siteEntry') ?? [] as $site) {
                    if (($site['permissionLevel'] ?? '') !== 'siteUnverifiedUser') {
                        $out['search_console'][] = ['id' => $site['siteUrl'], 'name' => $site['siteUrl'], 'group' => ''];
                    }
                }
            } else {
                $out['errors']['search_console'] = $fail($sc);
            }

            $accounts = $google()->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');
            if ($accounts->successful()) {
                foreach ($accounts->json('accounts') ?? [] as $acc) {
                    $loc = $google()->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$acc['name']}/locations", [
                        'readMask' => 'name,title,storefrontAddress', 'pageSize' => 100,
                    ]);
                    if ($loc->failed()) {
                        $out['errors']['google_business'] = $fail($loc);

                        continue;
                    }
                    foreach ($loc->json('locations') ?? [] as $l) {
                        $out['google_business'][] = [
                            'id' => Str::after($l['name'], 'locations/'),
                            'name' => $l['title'] ?? $l['name'],
                            'group' => $l['storefrontAddress']['locality'] ?? ($acc['accountName'] ?? ''),
                        ];
                    }
                }
            } else {
                $out['errors']['google_business'] = $fail($accounts);
            }

            return $out;
        });
    }
}
