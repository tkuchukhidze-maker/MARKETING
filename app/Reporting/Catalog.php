<?php

namespace App\Reporting;

/**
 * The reporting dictionary: which platforms can be connected, what each one
 * writes into the unified base, and the Georgian labels used on screen.
 *
 * Pure PHP (no framework calls) so the KPI maths can be tested on its own.
 */
final class Catalog
{
    /** Channel groups used to sum platforms together. */
    public const CHANNELS = [
        'paid'    => 'ფასიანი რეკლამა',
        'organic' => 'ორგანული სოც. მედია',
        'web'     => 'ვებსაიტი',
        'local'   => 'Google რუკა / ბიზნეს პროფილი',
        'search'  => 'ორგანული ძიება',
        'sales'   => 'გაყიდვები (POS)',
    ];

    /**
     * provider => [label, channel, colour, account-id label, credential fields, metrics it writes, push-only?]
     * Credential field => label. "refresh_token" fields use the shared Google app from .env.
     */
    public const PROVIDERS = [
        'facebook' => [
            'label' => 'Facebook გვერდი', 'channel' => 'organic', 'color' => '#1464D2',
            'account' => 'Page ID', 'fields' => ['access_token' => 'Page access token (long-lived)'],
            'metrics' => ['reach', 'impressions', 'engagement', 'new_followers', 'followers'],
        ],
        'instagram' => [
            'label' => 'Instagram ანგარიში', 'channel' => 'organic', 'color' => '#C1307E',
            'account' => 'Instagram Business Account ID', 'fields' => ['access_token' => 'Access token (long-lived)'],
            'metrics' => ['reach', 'impressions', 'engagement', 'profile_views', 'website_clicks', 'new_followers', 'followers'],
        ],
        'tiktok' => [
            'label' => 'TikTok ანგარიში', 'channel' => 'organic', 'color' => '#111111',
            'account' => 'Business ID (open_id)', 'fields' => ['access_token' => 'Access token'],
            'metrics' => ['impressions', 'engagement', 'profile_views', 'new_followers', 'followers'],
        ],
        'meta_ads' => [
            'label' => 'Meta Ads (FB + IG რეკლამა)', 'channel' => 'paid', 'color' => '#0866FF',
            'account' => 'Ad account ID (act_ გარეშე)', 'fields' => ['access_token' => 'System user access token'],
            'metrics' => ['spend', 'impressions', 'reach', 'clicks', 'conversions', 'conversion_value'],
        ],
        'google_ads' => [
            'label' => 'Google Ads', 'channel' => 'paid', 'color' => '#FBBC04',
            'account' => 'Customer ID (ტირეების გარეშე)', 'fields' => ['refresh_token' => 'Google OAuth refresh token', 'login_customer_id' => 'MCC ID (თუ არის)'],
            'metrics' => ['spend', 'impressions', 'clicks', 'conversions', 'conversion_value'],
        ],
        'tiktok_ads' => [
            'label' => 'TikTok Ads', 'channel' => 'paid', 'color' => '#FE2C55',
            'account' => 'Advertiser ID', 'fields' => ['access_token' => 'Access token'],
            'metrics' => ['spend', 'impressions', 'reach', 'clicks', 'conversions'],
        ],
        'ga4' => [
            'label' => 'Google Analytics 4', 'channel' => 'web', 'color' => '#E37400',
            'account' => 'Property ID (მხოლოდ ციფრები)', 'fields' => ['refresh_token' => 'Google OAuth refresh token'],
            'metrics' => ['sessions', 'users', 'new_users', 'engaged_sessions', 'key_events'],
        ],
        'google_business' => [
            'label' => 'Google Business Profile', 'channel' => 'local', 'color' => '#34A853',
            'account' => 'Location ID (მხოლოდ ციფრები)', 'fields' => ['refresh_token' => 'Google OAuth refresh token'],
            'metrics' => ['gbp_views', 'calls', 'directions', 'gbp_website_clicks', 'gbp_bookings'],
        ],
        'search_console' => [
            'label' => 'Google Search Console', 'channel' => 'search', 'color' => '#4285F4',
            'account' => 'საიტი (მაგ. https://batumissaklshi.ge/ ან sc-domain:batumissaklshi.ge)', 'fields' => ['refresh_token' => 'Google OAuth refresh token'],
            'metrics' => ['search_clicks', 'search_impressions', 'search_position_weighted'],
        ],
        'pos' => [
            'label' => 'POS / გაყიდვები', 'channel' => 'sales', 'color' => '#00854D',
            'account' => 'სისტემის სახელი (არასავალდებულო)', 'fields' => [],
            'metrics' => ['revenue', 'checks', 'guests', 'reservations', 'new_customers', 'marketing_revenue'],
            'push' => true,
        ],
    ];

    /** Metric => label. Units: money (GEL), count, ratio, rank. */
    public const METRICS = [
        'spend' => ['რეკლამის ხარჯი', 'money'],
        'impressions' => ['ნახვები (impressions)', 'count'],
        'reach' => ['მიწვდომა (reach)', 'count'],
        'clicks' => ['კლიკები', 'count'],
        'conversions' => ['კონვერსიები', 'count'],
        'conversion_value' => ['კონვერსიის ღირებულება', 'money'],
        'engagement' => ['ჩართულობა', 'count'],
        'new_followers' => ['ახალი გამომწერი', 'count'],
        'followers' => ['გამომწერები (სულ)', 'count'],
        'profile_views' => ['პროფილის ნახვა', 'count'],
        'website_clicks' => ['ბმულზე გადასვლა', 'count'],
        'sessions' => ['სესიები', 'count'],
        'users' => ['მომხმარებლები', 'count'],
        'new_users' => ['ახალი მომხმარებელი', 'count'],
        'engaged_sessions' => ['ჩართული სესიები', 'count'],
        'key_events' => ['მიზნობრივი მოქმედებები (GA4)', 'count'],
        'gbp_views' => ['პროფილის ნახვა Google-ზე', 'count'],
        'calls' => ['ზარები', 'count'],
        'directions' => ['მარშრუტის მოთხოვნა', 'count'],
        'gbp_website_clicks' => ['საიტზე გადასვლა Google-დან', 'count'],
        'gbp_bookings' => ['ჯავშანი Google-დან', 'count'],
        'search_clicks' => ['ძიების კლიკები', 'count'],
        'search_impressions' => ['ძიების ნახვები', 'count'],
        'search_position_weighted' => ['პოზიცია × ნახვა (ტექნიკური)', 'count'],
        'revenue' => ['შემოსავალი', 'money'],
        'checks' => ['ჩეკები / შეკვეთები', 'count'],
        'guests' => ['სტუმრები', 'count'],
        'reservations' => ['ჯავშნები', 'count'],
        'new_customers' => ['ახალი სტუმარი', 'count'],
        'marketing_revenue' => ['მარკეტინგიდან მოსული შემოსავალი', 'money'],
    ];

    /** Snapshot metrics: the period value is the last day's value, not the sum. */
    public const STOCK_METRICS = ['followers'];

    /** Metrics multiplied by a source's currency rate when they arrive. */
    public const MONEY_METRICS = ['spend', 'conversion_value', 'revenue', 'marketing_revenue'];

    public const COST_CATEGORIES = [
        'agency'     => 'სააგენტო / ფრილანსერი',
        'influencer' => 'ინფლუენსერი',
        'production' => 'გადაღება / პროდაქშენი',
        'print'      => 'ბეჭდვა / გარე რეკლამა',
        'event'      => 'ივენთი / აქტივაცია',
        'ads_other'  => 'სხვა რეკლამა (რადიო, TV, საიტები)',
        'tools'      => 'პროგრამები / სერვისები',
        'salary'     => 'მარკეტინგის გუნდი',
        'other'      => 'სხვა',
    ];

    /** Metrics a monthly target can be set for (KPI key => label). */
    public const TARGETABLE = [
        'revenue' => 'შემოსავალი',
        'marketing_spend' => 'მარკეტინგის ბიუჯეტი (მაქს.)',
        'romi' => 'ROMI %',
        'reservations' => 'ჯავშნები',
        'new_customers' => 'ახალი სტუმარი',
        'organic_reach' => 'ორგანული მიწვდომა',
        'new_followers' => 'ახალი გამომწერი',
        'sessions' => 'საიტის სესიები',
        'local_actions' => 'Google-ის ქმედებები (ზარი+მარშრუტი+საიტი)',
    ];

    public static function providerLabel(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? $provider;
    }

    public static function channelOf(string $provider): string
    {
        return self::PROVIDERS[$provider]['channel'] ?? 'other';
    }

    public static function providersIn(string $channel): array
    {
        return array_keys(array_filter(self::PROVIDERS, fn ($p) => $p['channel'] === $channel));
    }

    public static function metricLabel(string $metric): string
    {
        return self::METRICS[$metric][0] ?? $metric;
    }
}
