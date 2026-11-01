<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Google Analytics 4 Data API: daily website traffic and key events. */
class Ga4Connector extends GoogleConnector
{
    private const METRICS = [
        'sessions' => 'sessions',
        'totalUsers' => 'users',
        'newUsers' => 'new_users',
        'engagedSessions' => 'engaged_sessions',
        'keyEvents' => 'key_events',
    ];

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $property = preg_replace('/\D/', '', (string) $source->account_id);
        $json = $this->check(
            $this->http()->withToken($this->accessToken($source))->post("https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport", [
                'dateRanges' => [['startDate' => $from->toDateString(), 'endDate' => $to->toDateString()]],
                'dimensions' => [['name' => 'date']],
                'metrics' => array_map(fn ($m) => ['name' => $m], array_keys(self::METRICS)),
                'limit' => 1000,
            ]),
            'GA4'
        );

        $ours = array_values(self::METRICS);
        $rows = [];
        foreach ($json['rows'] ?? [] as $r) {
            $date = CarbonImmutable::createFromFormat('Ymd', $r['dimensionValues'][0]['value'])->toDateString();
            foreach ($r['metricValues'] ?? [] as $i => $v) {
                $rows[] = $this->row($date, $ours[$i], $v['value'] ?? 0);
            }
        }

        return $rows;
    }
}
