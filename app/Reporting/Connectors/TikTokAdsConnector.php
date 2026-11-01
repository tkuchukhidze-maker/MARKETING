<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** TikTok Ads: daily campaign report (report/integrated/get). */
class TikTokAdsConnector extends TikTokConnector
{
    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $rows = [];
        for ($page = 1, $pages = 1; $page <= $pages && $page <= 50; $page++) {
            $data = $this->call($source, 'report/integrated/get/', [
                'advertiser_id' => $source->account_id,
                'report_type' => 'BASIC',
                'data_level' => 'AUCTION_CAMPAIGN',
                'dimensions' => json_encode(['campaign_id', 'stat_time_day']),
                'metrics' => json_encode(['campaign_name', 'spend', 'impressions', 'reach', 'clicks', 'conversion']),
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'page' => $page,
                'page_size' => 1000,
            ], 'TikTok Ads');

            foreach ($data['list'] ?? [] as $r) {
                $date = $this->date($r['dimensions']['stat_time_day']);
                $m = $r['metrics'] ?? [];
                $campaign = (string) ($m['campaign_name'] ?? $r['dimensions']['campaign_id'] ?? '');
                $rows[] = $this->row($date, 'spend', $m['spend'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'impressions', $m['impressions'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'reach', $m['reach'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'clicks', $m['clicks'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'conversions', $m['conversion'] ?? 0, $campaign);
            }

            $pages = (int) ($data['page_info']['total_page'] ?? 1);
        }

        return $rows;
    }
}
