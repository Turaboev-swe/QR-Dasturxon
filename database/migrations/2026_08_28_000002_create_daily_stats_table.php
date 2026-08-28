<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-restaurant, per-day aggregate counters — bumped incrementally by
     * App\Services\DailyStatsService as orders/waiter-calls/reviews/sessions
     * happen (see that class), rather than computed on the fly from `orders`
     * etc. every time the SaaS operator dashboard is opened.
     */
    public function up(): void
    {
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('orders_total_amount')->default(0);
            $table->unsignedInteger('waiter_calls_count')->default(0);
            $table->unsignedInteger('bill_requests_count')->default(0);
            $table->unsignedInteger('unique_users_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();

            $table->unique(['restaurant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
