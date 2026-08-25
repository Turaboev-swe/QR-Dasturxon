<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\TelegramUser;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantTable $table;

    private TelegramUser $owner;

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
            'name' => 'Stol 1',
            'is_active' => true,
        ]);

        $this->owner = TelegramUser::create([
            'telegram_id' => 111222333,
            'first_name' => 'Owner',
        ]);
    }

    private function initDataHeaderFor(int $telegramId): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Test', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    private function createOrder(string $status): Order
    {
        return Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => $this->owner->id,
            'status' => $status,
            'total_price' => 45000,
        ]);
    }

    public function test_cannot_review_an_order_that_is_not_yours(): void
    {
        $order = $this->createOrder(Order::STATUS_PAID);

        $response = $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'rating' => 5,
        ], $this->initDataHeaderFor(999888777));

        $response->assertStatus(404);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_cannot_review_an_order_that_is_not_yet_completed(): void
    {
        $order = $this->createOrder(Order::STATUS_PENDING);

        $response = $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'rating' => 5,
        ], $this->initDataHeaderFor($this->owner->telegram_id));

        $response->assertStatus(422);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_can_review_a_completed_order(): void
    {
        $order = $this->createOrder(Order::STATUS_PAID);

        $response = $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'rating' => 4,
            'comment' => 'Juda mazali edi',
        ], $this->initDataHeaderFor($this->owner->telegram_id));

        $response->assertStatus(201)
            ->assertJson([
                'review' => [
                    'restaurant_id' => $this->restaurant->id,
                    'order_id' => $order->id,
                    'rating' => 4,
                    'comment' => 'Juda mazali edi',
                ],
            ]);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_cannot_review_the_same_order_twice(): void
    {
        $order = $this->createOrder(Order::STATUS_PAID);

        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'telegram_user_id' => $this->owner->id,
            'rating' => 5,
        ]);

        $response = $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'rating' => 1,
        ], $this->initDataHeaderFor($this->owner->telegram_id));

        $response->assertStatus(422);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $order = $this->createOrder(Order::STATUS_PAID);

        $response = $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'rating' => 6,
        ], $this->initDataHeaderFor($this->owner->telegram_id));

        $response->assertStatus(422);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_orders_endpoint_exposes_whether_an_order_has_been_reviewed(): void
    {
        $reviewed = $this->createOrder(Order::STATUS_PAID);
        $unreviewed = $this->createOrder(Order::STATUS_PAID);

        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $reviewed->id,
            'telegram_user_id' => $this->owner->id,
            'rating' => 5,
        ]);

        $response = $this->getJson('/api/orders', $this->initDataHeaderFor($this->owner->telegram_id));

        $response->assertStatus(200);
        $orders = collect($response->json('orders'));
        $this->assertNotNull($orders->firstWhere('id', $reviewed->id)['review']);
        $this->assertNull($orders->firstWhere('id', $unreviewed->id)['review']);
    }
}
