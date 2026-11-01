<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Google Ads API: daily campaign cost and conversions (GAQL searchStream). */
class GoogleAdsConnector extends GoogleConnector
{
    public function isConfigured(DataSource $source): bool
    {
        return parent::isConfigured($source) && config('reporting.google.ads_developer_token');
    }

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $customer = preg_replace('/\D/', '', (string) $source->account_id);
        $headers = ['developer-token' => config('reporting.google.ads_developer_token')];
        if ($login = $source->credential('login_customer_id')) {
            $headers['login-customer-id'] = preg_replace('/\D/', '', $login);
        }

        $gaql = sprintf(
            "SELECT segments.date, campaign.name, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value
             FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'",
            $from->toDateString(), $to->toDateString()
        );

        $json = $this->check(
            $this->http()->withToken($this->accessToken($source))->withHeaders($headers)
                ->post('https://googleads.googleapis.com/'.config('reporting.google.ads_version')."/customers/{$customer}/googleAds:searchStream", ['query' => $gaql]),
            'Google Ads'
        );

        $rows = [];
        foreach ($json as $chunk) {
            foreach ($chunk['results'] ?? [] as $r) {
                $date = $r['segments']['date'];
                $campaign = (string) ($r['campaign']['name'] ?? '');
                $m = $r['metrics'] ?? [];
                $rows[] = $this->row($date, 'spend', ((float) ($m['costMicros'] ?? 0)) / 1_000_000, $campaign);
                $rows[] = $this->row($date, 'impressions', $m['impressions'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'clicks', $m['clicks'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'conversions', $m['conversions'] ?? 0, $campaign);
                $rows[] = $this->row($date, 'conversion_value', $m['conversionsValue'] ?? 0, $campaign);
            }
        }

        return $rows;
    }
}
