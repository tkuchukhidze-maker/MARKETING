<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMO reporting: one shared base for every brand and every platform.
 *
 * - venues get reporting settings (margin, attribution share) and a flag so a
 *   brand can be reported on without appearing on the social media board;
 * - data_sources: one row per connected account (Meta page, ad account,
 *   GA4 property, POS, ...), with encrypted credentials;
 * - metrics_daily: every number from every platform, one row per
 *   brand / source / campaign / day / metric — the unified base;
 * - marketing_costs: spend that no API knows about (agency, influencers, print);
 * - kpi_targets: monthly plan per brand and metric.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->boolean('in_studio')->default(true)->after('sort');
            $table->string('kind', 20)->default('restaurant')->after('in_studio');
            $table->decimal('gross_margin', 5, 4)->default(0.65)->after('kind');
            $table->decimal('attribution_share', 5, 4)->default(0.30)->after('gross_margin');
        });

        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->index();
            $table->string('label');
            $table->string('account_id')->nullable();
            $table->text('credentials')->nullable();
            $table->decimal('currency_rate', 12, 6)->default(1);
            $table->string('ingest_token', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 10)->default('never');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 40);
            $table->string('provider', 30);
            $table->string('channel', 20);
            $table->string('campaign', 191)->default('');
            $table->date('date');
            $table->string('metric', 40);
            $table->decimal('value', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'source_key', 'campaign', 'date', 'metric'], 'metrics_daily_unique');
            $table->index(['venue_id', 'date']);
            $table->index(['provider', 'metric', 'date']);
        });

        Schema::create('marketing_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on')->index();
            $table->string('category', 30);
            $table->string('channel', 20)->default('other');
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('metric', 40);
            $table->decimal('target', 18, 4);
            $table->timestamps();

            $table->unique(['venue_id', 'month', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('marketing_costs');
        Schema::dropIfExists('metrics_daily');
        Schema::dropIfExists('data_sources');
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['in_studio', 'kind', 'gross_margin', 'attribution_share']);
        });
    }
};
