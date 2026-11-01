<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Google Business Profile Performance API: maps/search views, calls, directions. */
class GoogleBusinessConnector extends GoogleConnector
{
    private const METRICS = [
        'BUSINESS_IMPRESSIONS_DESKTOP_MAPS' => 'gbp_views',
        'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => 'gbp_views',
        'BUSINESS_IMPRESSIONS_MOBILE_MAPS' => 'gbp_views',
        'BUSINESS_IMPRESSIONS_MOBILE_SEARCH' => 'gbp_views',
        'CALL_CLICKS' => 'calls',
        'BUSINESS_DIRECTION_REQUESTS' => 'directions',
        'WEBSITE_CLICKS' => 'gbp_website_clicks',
        'BUSINESS_BOOKINGS' => 'gbp_bookings',
    ];

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $location = preg_replace('/\D/', '', (string) $source->account_id);
        // dailyMetrics repeats, so the query string is built by hand
        $query = implode('&', array_map(fn ($m) => 'dailyMetrics='.$m, array_keys(self::METRICS))).'&'.http_build_query([
            'dailyRange.start_date.year' => $from->year, 'dailyRange.start_date.month' => $from->month, 'dailyRange.start_date.day' => $from->day,
            'dailyRange.end_date.year' => $to->year, 'dailyRange.end_date.month' => $to->month, 'dailyRange.end_date.day' => $to->day,
        ]);

        $json = $this->check(
            $this->http()->withToken($this->accessToken($source))
                ->get("https://businessprofileperformance.googleapis.com/v1/locations/{$location}:fetchMultiDailyMetricsTimeSeries?{$query}"),
            'Google Business Profile'
        );

        $rows = [];
        foreach ($json['multiDailyMetricTimeSeries'] ?? [] as $group) {
            foreach ($group['dailyMetricTimeSeries'] ?? [] as $series) {
                $ours = self::METRICS[$series['dailyMetric'] ?? ''] ?? null;
                foreach ($series['timeSeries']['datedValues'] ?? [] as $point) {
                    if (! $ours) {
                        continue;
                    }
                    $d = $point['date'];
                    $date = sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);
                    $rows[] = $this->row($date, $ours, $point['value'] ?? 0);
                }
            }
        }

        return $rows;   // the four impression series are summed into gbp_views on save
    }
}
