<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Google Search Console: daily organic search clicks, impressions, position. */
class SearchConsoleConnector extends GoogleConnector
{
    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $site = rawurlencode((string) $source->account_id);
        $json = $this->check(
            $this->http()->withToken($this->accessToken($source))->post("https://searchconsole.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query", [
                'startDate' => $from->toDateString(),
                'endDate' => $to->toDateString(),
                'dimensions' => ['date'],
                'rowLimit' => 1000,
            ]),
            'Search Console'
        );

        $rows = [];
        foreach ($json['rows'] ?? [] as $r) {
            $date = $r['keys'][0];
            $rows[] = $this->row($date, 'search_clicks', $r['clicks'] ?? 0);
            $rows[] = $this->row($date, 'search_impressions', $r['impressions'] ?? 0);
            // stored weighted so the period's average position can be summed correctly
            $rows[] = $this->row($date, 'search_position_weighted', ($r['position'] ?? 0) * ($r['impressions'] ?? 0));
        }

        return $rows;
    }
}
