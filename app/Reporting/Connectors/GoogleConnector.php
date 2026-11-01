<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Shared Google OAuth: trades a source's refresh token for an access token. */
abstract class GoogleConnector extends Connector
{
    public function required(): array
    {
        return ['refresh_token'];
    }

    public function isConfigured(DataSource $source): bool
    {
        return parent::isConfigured($source)
            && config('reporting.google.client_id')
            && config('reporting.google.client_secret');
    }

    protected function accessToken(DataSource $source): string
    {
        $refresh = $source->credential('refresh_token');

        return Cache::remember('google-token:'.sha1($refresh), now()->addMinutes(50), function () use ($refresh) {
            $json = $this->check(Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('reporting.google.client_id'),
                'client_secret' => config('reporting.google.client_secret'),
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]), 'Google OAuth');

            return $json['access_token'] ?? throw new ConnectorException('Google OAuth: access token არ დაბრუნდა.');
        });
    }
}
