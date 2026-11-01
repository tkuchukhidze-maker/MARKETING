<?php

namespace App\Models;

use App\Reporting\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One connected account: a Facebook page, an ad account, a GA4 property, a POS…
 * Credentials are stored encrypted with the app key.
 */
#[Fillable(['venue_id', 'provider', 'label', 'account_id', 'credentials', 'currency_rate', 'is_active'])]
#[Hidden(['credentials', 'ingest_token'])]
class DataSource extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'currency_rate' => 'decimal:6',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DataSource $source) {
            // push-only sources (POS) receive data at their own secret URL
            if (Catalog::PROVIDERS[$source->provider]['push'] ?? false) {
                $source->ingest_token ??= Str::random(48);
            }
        });
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(MetricDaily::class);
    }

    public function sourceKey(): string
    {
        return 'src:'.$this->id;
    }

    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function isPush(): bool
    {
        return (bool) (Catalog::PROVIDERS[$this->provider]['push'] ?? false);
    }

    public function providerLabel(): string
    {
        return Catalog::providerLabel($this->provider);
    }

    public function channel(): string
    {
        return Catalog::channelOf($this->provider);
    }
}
