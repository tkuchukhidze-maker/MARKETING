<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publishing is confirmed per platform (Facebook / Instagram / Story),
 * with who confirmed it, when, and a link to the live post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_channels', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('format');
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->string('post_url', 2048)->nullable()->after('published_by');
        });

        // tasks already marked published count as published on every platform
        $published = DB::table('tasks')->where('status', 'published')->pluck('id');
        if ($published->isNotEmpty()) {
            DB::table('task_channels')->whereIn('task_id', $published)->update(['published_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('task_channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['published_at', 'post_url']);
        });
    }
};
