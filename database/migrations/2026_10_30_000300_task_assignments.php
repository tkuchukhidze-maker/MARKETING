<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A task can now go to up to three people at once, each with their own part
 * and their own progress: assigned → in_progress → ready.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('note', 300)->nullable();
            $table->string('status', 20)->default('assigned')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        // carry over the single assignee each task had, with matching progress
        $now = now();
        DB::table('tasks')->whereNotNull('assignee_id')->orderBy('id')
            ->select(['id', 'assignee_id', 'status'])
            ->chunk(500, function ($tasks) use ($now) {
                DB::table('task_assignments')->insert($tasks->map(fn ($t) => [
                    'task_id' => $t->id,
                    'user_id' => $t->assignee_id,
                    'status' => match ($t->status) {
                        'in_progress', 'revision' => 'in_progress',
                        'review', 'approved', 'published' => 'ready',
                        default => 'assigned',
                    },
                    'started_at' => in_array($t->status, ['todo'], true) ? null : $now,
                    'ready_at' => in_array($t->status, ['review', 'approved', 'published'], true) ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('task_assignments')->orderBy('id')->get() as $a) {
            DB::table('tasks')->where('id', $a->task_id)->whereNull('assignee_id')->update(['assignee_id' => $a->user_id]);
        }

        Schema::dropIfExists('task_assignments');
    }
};
