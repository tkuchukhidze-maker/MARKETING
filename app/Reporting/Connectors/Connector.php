<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Pulls daily numbers from one platform. Every connector returns the same
 * row shape, which is what makes the base "unified":
 *   ['date' => 'Y-m-d', 'metric' => string, 'value' => float, 'campaign' => string]
 */
abstract class Connector
{
    /** Credential keys that must be filled before this source can be pulled. */
    abstract public function required(): array;

    /** @return iterable<array{date: string, metric: string, value: float, campaign?: string}> */
    abstract public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable;

    public function isConfigured(DataSource $source): bool
    {
        if (! $source->account_id) {
            return false;
        }

        foreach ($this->required() as $key) {
            if ($source->credential($key) === null) {
                return false;
            }
        }

        return true;
    }

    protected function http(): PendingRequest
    {
        // retry only network hiccups and platform 5xx — a 4xx (bad token) won't fix itself
        return Http::timeout(60)->connectTimeout(15)->acceptJson()->retry(2, 1500, function (\Throwable $e) {
            return $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError());
        }, throw: false);
    }

    protected function row(string $date, string $metric, mixed $value, string $campaign = ''): array
    {
        return ['date' => $date, 'metric' => $metric, 'value' => (float) $value, 'campaign' => mb_substr($campaign, 0, 191)];
    }

    /** Throw a readable error when a platform answers with a failure. */
    protected function check(Response $response, string $platform): array
    {
        if ($response->failed()) {
            $json = $response->json();
            $message = $json['error']['message']
                ?? $json['error_description']
                ?? $json['message']
                ?? $json[0]['error']['message']
                ?? mb_substr($response->body(), 0, 300);

            throw new ConnectorException("{$platform}: HTTP {$response->status()} — {$message}");
        }

        return $response->json() ?? [];
    }

    /** @return list<CarbonImmutable> */
    protected function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $days[] = $d;
        }

        return $days;
    }
}
