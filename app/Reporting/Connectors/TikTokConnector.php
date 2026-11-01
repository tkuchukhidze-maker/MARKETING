<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/** Shared TikTok Business API call: Access-Token header, JSON "code" envelope. */
abstract class TikTokConnector extends Connector
{
    public function required(): array
    {
        return ['access_token'];
    }

    protected function call(DataSource $source, string $path, array $query, string $platform): array
    {
        $response = $this->http()->withHeaders(['Access-Token' => $source->credential('access_token')])
            ->get(rtrim(config('reporting.tiktok.base_url'), '/').'/'.$path, $query);
        $json = $this->check($response, $platform);

        if (($json['code'] ?? 0) !== 0) {
            throw new ConnectorException("{$platform}: ".($json['message'] ?? 'უცნობი შეცდომა'));
        }

        return $json['data'] ?? [];
    }

    protected function date(string $value): string
    {
        return CarbonImmutable::parse($value)->toDateString();
    }
}
