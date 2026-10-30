<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One task can now target several platforms, each with its own format,
 * carries reference links, and accepts finished work as a link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->index();
            $table->string('format', 60)->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'platform']);
        });

        // carry over the single platform/format every existing task had
        $now = now();
        DB::table('tasks')->orderBy('id')->select(['id', 'platform', 'format'])
            ->chunk(500, function ($tasks) use ($now) {
                DB::table('task_channels')->insert($tasks->map(fn ($t) => [
                    'task_id' => $t->id,
                    'platform' => $t->platform,
                    'format' => $t->format,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['platform']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['platform', 'format']);
            $table->json('reference_links')->nullable()->after('brief');
        });

        Schema::table('task_assets', function (Blueprint $table) {
            $table->string('kind', 10)->default('file')->after('version');
            $table->string('url', 2048)->nullable()->after('kind');
            $table->foreignId('task_channel_id')->nullable()->after('task_id')
                ->constrained('task_channels')->nullOnDelete();
            $table->string('disk', 30)->nullable()->change();
            $table->string('path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_channel_id');
            $table->dropColumn(['kind', 'url']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('platform', 20)->default('facebook')->index();
            $table->string('format', 60)->nullable();
            $table->dropColumn('reference_links');
        });

        foreach (DB::table('task_channels')->orderBy('id')->get() as $c) {
            DB::table('tasks')->where('id', $c->task_id)->update(['platform' => $c->platform, 'format' => $c->format]);
        }

        Schema::dropIfExists('task_channels');
    }
};
