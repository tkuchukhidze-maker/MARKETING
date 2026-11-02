<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A written analysis (audit, inbox review…) for one brand, with its action checklist. */
#[Fillable(['venue_id', 'user_id', 'slug', 'title', 'period_from', 'period_to', 'summary', 'body'])]
class AnalysisReport extends Model
{
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ReportAction::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Markdown → HTML. Raw HTML in the upload is escaped and unsafe links
     * (javascript:, data:) are dropped, so an uploaded file can't inject script.
     */
    public function html(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 80, '') ?: 'analizi';
        $base = $base.'-'.now()->format('Ymd');
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
