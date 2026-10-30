<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('smm')->after('email');
            $table->string('avatar_color', 7)->default('#1F76C2')->after('role');
            $table->boolean('is_active')->default(true)->after('avatar_color');
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#0B7A83');
            $table->text('concept')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('platform', 20)->index();
            $table->string('format', 60)->nullable();
            $table->text('brief')->nullable();
            $table->string('status', 20)->default('todo')->index();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('due_on')->nullable()->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('task_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('artist');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('instagram')->nullable();
            $table->string('next_step')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('task_assets');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('venues');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar_color', 'is_active']);
        });
    }
};
