<?php

namespace App\Reporting\Connectors;

/** provider key => connector class. */
final class Registry
{
    public const MAP = [
        'facebook' => MetaPageConnector::class,
        'instagram' => InstagramConnector::class,
        'meta_ads' => MetaAdsConnector::class,
        'google_ads' => GoogleAdsConnector::class,
        'ga4' => Ga4Connector::class,
        'google_business' => GoogleBusinessConnector::class,
        'search_console' => SearchConsoleConnector::class,
        'tiktok' => TikTokOrganicConnector::class,
        'tiktok_ads' => TikTokAdsConnector::class,
        'pos' => PosConnector::class,
    ];

    public static function for(string $provider): Connector
    {
        $class = self::MAP[$provider] ?? throw new ConnectorException("უცნობი პლატფორმა: {$provider}");

        return app($class);
    }
}
