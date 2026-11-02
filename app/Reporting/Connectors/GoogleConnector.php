<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use App\Models\OAuthConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Shared Google OAuth: trades a source's refresh token for an access token. */
abstract class GoogleConnector extends Connector
{
    /** A pasted refresh token is optional: the shared "Connect Google" sign-in is used otherwise. */
    public function required(): array
    {
        return [];
    }

    public function isConfigured(DataSource $source): bool
    {
        return parent::isConfigured($source)
            && config('reporting.google.client_id')
            && config('reporting.google.client_secret')
            && $this->refreshToken($source) !== null;
    }

    protected function refreshToken(DataSource $source): ?string
    {
        return $source->credential('refresh_token') ?? OAuthConnection::google()?->refresh_token;
    }

    protected function accessToken(DataSource $source): string
    {
        $refresh = $this->refreshToken($source) ?? throw new ConnectorException('Google: ანგარიში დაკავშირებული არ არის.');

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
