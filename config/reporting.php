<?php

/*
 * CMO reporting — platform API settings.
 *
 * Platform APIs change their versions and metric names over time. Everything
 * that is likely to change lives here, so it can be adjusted in .env or in
 * this file without touching the connectors.
 */
return [
    // The brand currency. Spend reported in another currency is converted with
    // each source's "currency rate" (e.g. USD ad account → 2.70).
    'currency' => env('REPORTING_CURRENCY', '₾'),

    // Days re-fetched by the nightly sync (platforms revise recent days).
    'sync_days' => (int) env('REPORTING_SYNC_DAYS', 3),

    // When the nightly sync runs (Tbilisi time).
    'sync_at' => env('REPORTING_SYNC_AT', '06:30'),

    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),

        // Page insights metric => our metric. Each is requested separately, so
        // one retired metric does not stop the others.
        'page_metrics' => [
            'page_impressions_unique' => 'reach',
            'page_media_view' => 'impressions',
            'page_post_engagements' => 'engagement',
            'page_daily_follows_unique' => 'new_followers',
        ],

        // Instagram account insights (metric_type=total_value, one day at a time).
        'instagram_metrics' => [
            'reach' => 'reach',
            'views' => 'impressions',
            'total_interactions' => 'engagement',
            'profile_views' => 'profile_views',
            'website_clicks' => 'website_clicks',
        ],

        // Ad "actions" counted as a conversion for a restaurant / hotel:
        // messages started, leads, calls, purchases/bookings via pixel.
        'conversion_actions' => array_filter(explode(',', env('META_CONVERSION_ACTIONS',
            'onsite_conversion.messaging_conversation_started_7d,lead,onsite_conversion.lead_grouped,'
            .'offsite_conversion.fb_pixel_purchase,offsite_conversion.fb_pixel_lead,click_to_call_native_call_placed'
        ))),
        'value_actions' => array_filter(explode(',', env('META_VALUE_ACTIONS', 'offsite_conversion.fb_pixel_purchase,omni_purchase'))),
    ],

    'google' => [
        // One OAuth app (Google Cloud → Credentials → OAuth client) for GA4,
        // Google Ads, Business Profile and Search Console. Each source stores
        // its own refresh token.
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'ads_developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'ads_version' => env('GOOGLE_ADS_API_VERSION', 'v22'),
    ],

    'tiktok' => [
        'base_url' => env('TIKTOK_API_BASE', 'https://business-api.tiktok.com/open_api/v1.3/'),
    ],
];
