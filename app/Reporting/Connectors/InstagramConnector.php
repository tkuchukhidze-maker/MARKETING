<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Instagram professional account insights (Graph API /{ig-user-id}/insights). */
class InstagramConnector extends Connector
{
    public function required(): array
    {
        return ['access_token'];
    }

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $base = 'https://graph.facebook.com/'.config('reporting.meta.graph_version').'/';
        $token = $source->credential('access_token');
        $metrics = config('reporting.meta.instagram_metrics');
        $rows = [];

        // total_value metrics are totals for the range, so ask one day at a time
        foreach ($this->days($from, $to) as $day) {
            $json = $this->check($this->http()->get($base.$source->account_id.'/insights', [
                'metric' => implode(',', array_keys($metrics)),
                'period' => 'day',
                'metric_type' => 'total_value',
                'since' => $day->startOfDay()->getTimestamp(),
                'until' => $day->addDay()->startOfDay()->getTimestamp(),
                'access_token' => $token,
            ]), 'Instagram');

            foreach ($json['data'] ?? [] as $item) {
                $ours = $metrics[$item['name'] ?? ''] ?? null;
                $value = $item['total_value']['value'] ?? null;
                if ($ours && is_numeric($value)) {
                    $rows[] = $this->row($day->toDateString(), $ours, $value);
                }
            }
        }

        // new followers per day (Instagram keeps the last 30 days)
        $since = max($from->getTimestamp(), today()->subDays(29)->getTimestamp());
        $followers = $this->http()->get($base.$source->account_id.'/insights', [
            'metric' => 'follower_count',
            'period' => 'day',
            'since' => $since,
            'until' => $to->addDay()->getTimestamp(),
            'access_token' => $token,
        ]);
        if ($followers->successful()) {
            foreach ($followers->json('data.0.values') ?? [] as $point) {
                $date = CarbonImmutable::parse($point['end_time'])->subDay()->toDateString();
                if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                    $rows[] = $this->row($date, 'new_followers', $point['value'] ?? 0);
                }
            }
        }

        $account = $this->http()->get($base.$source->account_id, ['fields' => 'followers_count', 'access_token' => $token]);
        if ($account->successful() && is_numeric($account->json('followers_count'))) {
            $rows[] = $this->row(min(today()->toDateString(), $to->toDateString()), 'followers', $account->json('followers_count'));
        }

        return $rows;
    }
}
