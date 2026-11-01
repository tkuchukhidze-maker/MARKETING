<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** TikTok account (Business API, business/get): daily views, engagement, followers. */
class TikTokOrganicConnector extends TikTokConnector
{
    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $data = $this->call($source, 'business/get/', [
            'business_id' => $source->account_id,
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
            'fields' => json_encode(['followers_count', 'metrics']),
        ], 'TikTok');

        $rows = [];
        $previous = null;
        $daily = $data['metrics'] ?? [];
        usort($daily, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        foreach ($daily as $d) {
            if (empty($d['date'])) {
                continue;
            }
            $date = $this->date($d['date']);
            $rows[] = $this->row($date, 'impressions', $d['video_views'] ?? 0);
            $rows[] = $this->row($date, 'profile_views', $d['profile_views'] ?? 0);
            $rows[] = $this->row($date, 'engagement', ($d['likes'] ?? 0) + ($d['comments'] ?? 0) + ($d['shares'] ?? 0));
            if (isset($d['followers_count'])) {
                $rows[] = $this->row($date, 'followers', $d['followers_count']);
                if ($previous !== null) {
                    $rows[] = $this->row($date, 'new_followers', max(0, $d['followers_count'] - $previous));
                }
                $previous = $d['followers_count'];
            }
        }

        if ($daily === [] && isset($data['followers_count'])) {
            $rows[] = $this->row(min(today()->toDateString(), $to->toDateString()), 'followers', $data['followers_count']);
        }

        return $rows;
    }
}
