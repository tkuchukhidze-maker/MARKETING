<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Facebook Page organic insights (Graph API /{page-id}/insights). */
class MetaPageConnector extends Connector
{
    public function required(): array
    {
        return ['access_token'];
    }

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $base = 'https://graph.facebook.com/'.config('reporting.meta.graph_version').'/';
        $token = $source->credential('access_token');
        $rows = [];
        $errors = [];

        // one request per metric: a metric Meta retires must not block the rest
        foreach (config('reporting.meta.page_metrics') as $metaMetric => $ours) {
            try {
                $json = $this->check($this->http()->get($base.$source->account_id.'/insights', [
                    'metric' => $metaMetric,
                    'period' => 'day',
                    'since' => $from->toDateString(),
                    'until' => $to->addDay()->toDateString(),
                    'access_token' => $token,
                ]), 'Facebook');
            } catch (ConnectorException $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            foreach ($json['data'] ?? [] as $series) {
                foreach ($series['values'] ?? [] as $point) {
                    // end_time marks the end of the day the value belongs to
                    $date = CarbonImmutable::parse($point['end_time'])->subDay()->toDateString();
                    if ($date >= $from->toDateString() && $date <= $to->toDateString() && is_numeric($point['value'] ?? null)) {
                        $rows[] = $this->row($date, $ours, $point['value']);
                    }
                }
            }
        }

        // follower total is a snapshot: store it for today
        $page = $this->http()->get($base.$source->account_id, ['fields' => 'followers_count,fan_count', 'access_token' => $token]);
        if ($page->successful()) {
            $count = $page->json('followers_count') ?? $page->json('fan_count');
            if (is_numeric($count)) {
                $rows[] = $this->row(min(today()->toDateString(), $to->toDateString()), 'followers', $count);
            }
        }

        if ($rows === [] && $errors !== []) {
            throw new ConnectorException(implode(' | ', array_unique($errors)));
        }

        return $rows;
    }
}
