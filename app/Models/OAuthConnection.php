<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** A signed-in platform account (e.g. Google). The refresh token is encrypted with APP_KEY. */
#[Fillable(['provider', 'email', 'refresh_token', 'scopes', 'user_id'])]
#[Hidden(['refresh_token'])]
class OAuthConnection extends Model
{
    protected $table = 'oauth_connections';

    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }

    public static function google(): ?self
    {
        return static::where('provider', 'google')->first();
    }
}
