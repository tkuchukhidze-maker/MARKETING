<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Meta Marketing API: daily campaign insights of one ad account (FB + IG ads). */
class MetaAdsConnector extends Connector
{
    public function required(): array
    {
        return ['access_token'];
    }

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $account = 'act_'.ltrim((string) $source->account_id, 'act_');
        $url = 'https://graph.facebook.com/'.config('reporting.meta.graph_version').'/'.$account.'/insights';
        $query = [
            'level' => 'campaign',
            'time_increment' => 1,
            'fields' => 'campaign_name,spend,impressions,reach,clicks,actions,action_values',
            'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()]),
            'limit' => 500,
            'access_token' => $source->credential('access_token'),
        ];
        $conversionTypes = config('reporting.meta.conversion_actions');
        $valueTypes = config('reporting.meta.value_actions');
        $rows = [];

        for ($page = 0; $url && $page < 50; $page++) {
            $json = $this->check($this->http()->get($url, $query), 'Meta Ads');
            $query = [];   // the "next" link already carries every parameter

            foreach ($json['data'] ?? [] as $r) {
                $date = $r['date_start'];
                $campaign = (string) ($r['campaign_name'] ?? '');
                foreach (['spend', 'impressions', 'reach', 'clicks'] as $m) {
                    $rows[] = $this->row($date, $m, $r[$m] ?? 0, $campaign);
                }
                $rows[] = $this->row($date, 'conversions', $this->sumActions($r['actions'] ?? [], $conversionTypes), $campaign);
                $rows[] = $this->row($date, 'conversion_value', $this->sumActions($r['action_values'] ?? [], $valueTypes), $campaign);
            }

            $url = $json['paging']['next'] ?? null;
        }

        return $rows;
    }

    private function sumActions(array $actions, array $types): float
    {
        $sum = 0.0;
        foreach ($actions as $a) {
            if (in_array($a['action_type'] ?? '', $types, true)) {
                $sum += (float) ($a['value'] ?? 0);
            }
        }

        return $sum;
    }
}
