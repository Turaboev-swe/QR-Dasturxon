<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DailyStatsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => '1',
            'is_active' => true,
        ]);
    }

    private function customerHeader(int $telegramId = 111222333, ?string $restaurantIdOverride = null): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'start_param' => 'r'.($restaurantIdOverride ?? $this->restaurant->id).'_t1',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Test', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    private function statFor(Restaurant $restaurant): ?object
    {
        return DB::table('daily_stats')->where('restaurant_id', $restaurant->id)->first();
    }

    private function createDish(): Dish
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_translations' => ['uz' => 'Taomlar'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Osh'],
            'price' => 20000,
            'sort_order' => 1,
            'is_available' => true,
        ]);
    }

    public function test_three_orders_increment_orders_count_to_three(): void
    {
        $dish = $this->createDish();

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/orders', [
                'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            ], $this->customerHeader())->assertStatus(201);
        }

        $stat = $this->statFor($this->restaurant);

        $this->assertNotNull($stat);
        $this->assertSame(3, $stat->orders_count);
        $this->assertSame(60000, $stat->orders_total_amount);
    }

    public function test_a_user_opening_the_mini_app_five_times_in_one_day_only_counts_once(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/session', [], $this->customerHeader(111222333))->assertStatus(200);
        }

        $stat = $this->statFor($this->restaurant);

        $this->assertSame(1, $stat->unique_users_count);
        $this->assertSame(1, DB::table('daily_restaurant_visits')->where('restaurant_id', $this->restaurant->id)->count());
    }

    public function test_a_different_user_the_same_day_increments_unique_users_count_again(): void
    {
        $this->postJson('/api/session', [], $this->customerHeader(111))->assertStatus(200);
        $this->postJson('/api/session', [], $this->customerHeader(222))->assertStatus(200);

        $stat = $this->statFor($this->restaurant);

        $this->assertSame(2, $stat->unique_users_count);
    }

    public function test_waiter_call_and_bill_request_increment_separate_columns(): void
    {
        $this->postJson('/api/waiter-calls', ['type' => 'waiter'], $this->customerHeader())->assertStatus(201);
        $this->postJson('/api/waiter-calls', ['type' => 'bill'], $this->customerHeader())->assertStatus(201);

        $stat = $this->statFor($this->restaurant);

        $this->assertSame(1, $stat->waiter_calls_count);
        $this->assertSame(1, $stat->bill_requests_count);
    }

    public function test_review_increments_reviews_count(): void
    {
        $this->postJson('/api/reviews', ['rating' => 5, 'comment' => 'Zo\'r!'], $this->customerHeader())
            ->assertStatus(201);

        $stat = $this->statFor($this->restaurant);

        $this->assertSame(1, $stat->reviews_count);
    }

    public function test_two_restaurants_stats_on_the_same_day_do_not_mix(): void
    {
        $otherRestaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Boshqa Restoran'],
            'latitude' => 40.0,
            'longitude' => 65.0,
            'radius_meters' => 150,
            'is_active' => true,
        ]);
        RestaurantTable::create(['restaurant_id' => $otherRestaurant->id, 'code' => '1', 'is_active' => true]);

        $otherHeader = $this->customerHeader(999888777, (string) $otherRestaurant->id);

        $this->postJson('/api/waiter-calls', [], $this->customerHeader())->assertStatus(201);

        $this->postJson('/api/waiter-calls', [], $otherHeader)->assertStatus(201);
        $this->postJson('/api/waiter-calls', ['type' => 'bill'], $otherHeader)->assertStatus(201);

        $mine = $this->statFor($this->restaurant);
        $theirs = $this->statFor($otherRestaurant);

        $this->assertSame(1, $mine->waiter_calls_count);
        $this->assertSame(0, $mine->bill_requests_count);

        $this->assertSame(1, $theirs->waiter_calls_count);
        $this->assertSame(1, $theirs->bill_requests_count);
    }

    public function test_a_daily_stats_write_failure_does_not_break_order_creation(): void
    {
        // Simulate stats storage being broken (e.g. a DB hiccup) by
        // dropping the table the service writes to — the order must
        // still be created successfully, with the failure only logged.
        Schema::drop('daily_stats');
        Log::spy();

        $dish = $this->createDish();

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        $this->assertDatabaseCount('orders', 1);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'daily_stats.record_failed'
                && $context['method'] === 'recordOrder');
    }

    public function test_a_daily_stats_write_failure_does_not_break_a_waiter_call(): void
    {
        Schema::drop('daily_stats');
        Log::spy();

        $response = $this->postJson('/api/waiter-calls', [], $this->customerHeader());

        $response->assertStatus(201);
        $this->assertDatabaseCount('waiter_calls', 1);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'daily_stats.record_failed'
                && $context['method'] === 'recordWaiterCall');
    }
}
