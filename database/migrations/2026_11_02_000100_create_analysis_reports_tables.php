<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Written analyses (Meta Ads audits, inbox reviews…) attached to a brand,
 * with an action checklist the team ticks off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analysis_reports')) {
            Schema::create('analysis_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('slug')->unique();
                $table->string('title');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->text('summary')->nullable();
                $table->longText('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('report_actions')) {
            Schema::create('report_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('analysis_report_id')->constrained()->cascadeOnDelete();
                $table->string('group', 120)->default('');
                $table->string('text', 500);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamp('done_at')->nullable();
                $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_actions');
        Schema::dropIfExists('analysis_reports');
    }
};
