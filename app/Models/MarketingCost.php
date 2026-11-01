<?php

namespace App\Models;

use App\Reporting\Catalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Marketing spend no API reports: agency, influencers, print, production… */
#[Fillable(['venue_id', 'user_id', 'spent_on', 'category', 'channel', 'amount', 'note'])]
class MarketingCost extends Model
{
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'float',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return Catalog::COST_CATEGORIES[$this->category] ?? $this->category;
    }
}
