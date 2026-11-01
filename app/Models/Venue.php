<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'color', 'concept', 'sort', 'in_studio', 'kind', 'gross_margin', 'attribution_share'])]
class Venue extends Model
{
    protected function casts(): array
    {
        return [
            'in_studio' => 'boolean',
            'gross_margin' => 'float',
            'attribution_share' => 'float',
        ];
    }

    /** Brands the social media team works on (Magnolia is reporting-only). */
    public function scopeStudio(Builder $query): Builder
    {
        return $query->where('in_studio', true)->orderBy('sort');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function dataSources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }

    public function marketingCosts(): HasMany
    {
        return $this->hasMany(MarketingCost::class);
    }
}
