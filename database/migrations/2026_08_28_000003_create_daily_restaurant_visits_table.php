<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal dedupe table backing daily_stats.unique_users_count — see
     * App\Services\DailyStatsService::recordVisit() for why this (a plain
     * MySQL table with a unique constraint) was chosen over Redis/cache:
     * this project has no Redis anywhere and CACHE_STORE=database already,
     * so a cache-based "set" would just be a less transparent version of
     * the same MySQL row; this way `INSERT ... IGNORE` gives an atomic,
     * race-safe "have we already counted this user today" check with a
     * real unique index, no per-key TTL bookkeeping, and it's trivially
     * inspectable with plain SQL. No model — it's a write-only, internal
     * implementation detail of DailyStatsService, nothing else reads it.
     */
    public function up(): void
    {
        Schema::create('daily_restaurant_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('created_at')->nullable();

            // Explicit short name — the auto-generated one exceeds MySQL's
            // 64-character identifier limit for this column combination.
            $table->unique(['restaurant_id', 'telegram_user_id', 'date'], 'daily_visits_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_restaurant_visits');
    }
};
