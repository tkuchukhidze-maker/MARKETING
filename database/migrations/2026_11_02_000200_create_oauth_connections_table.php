<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One signed-in account per platform (Google), shared by all its sources. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oauth_connections')) {
            Schema::create('oauth_connections', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 30)->unique();
                $table->string('email')->nullable();
                $table->text('refresh_token');
                $table->text('scopes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_connections');
    }
};
