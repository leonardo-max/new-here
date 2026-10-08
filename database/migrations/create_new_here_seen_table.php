<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('new-here.table', 'new_here_seen'), function (Blueprint $table): void {
            $table->id();
            $table->string('user_type');
            $table->string('user_id');
            $table->string('feature_key');
            $table->timestamp('seen_at');

            $table->unique(['user_type', 'user_id', 'feature_key']);
            $table->index(['user_type', 'user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('new-here.table', 'new_here_seen'));
    }
};
