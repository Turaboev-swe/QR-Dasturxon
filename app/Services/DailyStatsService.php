<?php

namespace App\Services;

use App\Models\DailyStat;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\TelegramUser;
use App\Models\WaiterCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single place that bumps `daily_stats` — every controller that
 * creates an order/waiter-call/review, or resolves a customer session,
 * calls one method here instead of touching the table itself (CLAUDE.md's
 * thin-controller rule). Every bump is race-safe under concurrent
 * requests: `insertOrIgnore()` guarantees a zeroed row exists for
 * (restaurant_id, date) without ever throwing on the unique index (two
 * concurrent first-events for the same restaurant/day just both no-op
 * past each other), then a plain `column = column + N` UPDATE does the
 * actual increment atomically at the database level.
 *
 * Every public method swallows its own failures (logged as
 * `daily_stats.record_failed`) — this is pure measurement, so a stats
 * write going wrong (e.g. a DB hiccup) must never take down the order/
 * waiter-call/review/session request it's attached to. Callers never
 * need their own try/catch around these calls.
 */
class DailyStatsService
{
    public function recordOrder(Order $order): void
    {
        $this->safely('recordOrder', ['order_id' => $order->id], function () use ($order) {
            $this->bump((int) $order->restaurant_id, [
                'orders_count' => 1,
                'orders_total_amount' => (int) round((float) $order->total_price),
            ]);
        });
    }

    public function recordWaiterCall(WaiterCall $call): void
    {
        $this->safely('recordWaiterCall', ['waiter_call_id' => $call->id], function () use ($call) {
            $column = $call->type === WaiterCall::TYPE_BILL ? 'bill_requests_count' : 'waiter_calls_count';

            $this->bump((int) $call->restaurant_id, [$column => 1]);
        });
    }

    public function recordReview(Review $review): void
    {
        $this->safely('recordReview', ['review_id' => $review->id], function () use ($review) {
            $this->bump((int) $review->restaurant_id, ['reviews_count' => 1]);
        });
    }

    /**
     * Called once per resolved Mini App session (SessionController::resolve
     * — the "session ochilganda" event). `unique_users_count` only grows
     * the first time a given telegram_user is seen for this restaurant on
     * this calendar day.
     *
     * Dedup approach: a dedicated `daily_restaurant_visits` table with a
     * unique (restaurant_id, telegram_user_id, date) index, rather than
     * Redis/cache. Reasoning: this project has no Redis anywhere and
     * CACHE_STORE=database already (see config/cache.php) — a cache "set"
     * here would just be a less transparent version of the same MySQL
     * row. A real table gives an atomic `INSERT ... IGNORE` for "have we
     * already counted this user today", needs no per-key TTL/expiry
     * bookkeeping (a cache-based approach would have to expire each key
     * at local midnight), survives restarts, and is trivially auditable
     * with plain SQL.
     */
    public function recordVisit(Restaurant $restaurant, TelegramUser $telegramUser): void
    {
        $this->safely('recordVisit', ['restaurant_id' => $restaurant->id, 'telegram_user_id' => $telegramUser->id], function () use ($restaurant, $telegramUser) {
            $date = Carbon::today()->toDateString();

            $isFirstVisitToday = DB::table('daily_restaurant_visits')->insertOrIgnore([
                'restaurant_id' => $restaurant->id,
                'telegram_user_id' => $telegramUser->id,
                'date' => $date,
                'created_at' => now(),
            ]) > 0;

            if ($isFirstVisitToday) {
                $this->bump((int) $restaurant->id, ['unique_users_count' => 1]);
            }
        });
    }

    /**
     * Runs $work(), catching and logging absolutely anything it throws
     * instead of letting it bubble up — see the class doc.
     */
    private function safely(string $method, array $context, \Closure $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            Log::warning('daily_stats.record_failed', [
                'method' => $method,
                ...$context,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $increments  column => amount to add
     */
    private function bump(int $restaurantId, array $increments): void
    {
        $date = Carbon::today()->toDateString();

        DailyStat::query()->insertOrIgnore([[
            'restaurant_id' => $restaurantId,
            'date' => $date,
            'orders_count' => 0,
            'orders_total_amount' => 0,
            'waiter_calls_count' => 0,
            'bill_requests_count' => 0,
            'unique_users_count' => 0,
            'reviews_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        $updates = ['updated_at' => now()];

        foreach ($increments as $column => $amount) {
            $updates[$column] = DB::raw("{$column} + ".(int) $amount);
        }

        DailyStat::query()
            ->where('restaurant_id', $restaurantId)
            ->where('date', $date)
            ->update($updates);
    }
}
