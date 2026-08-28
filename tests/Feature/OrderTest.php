<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\TelegramUser;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantTable $table;

    private Dish $dish;

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

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_translations' => ['uz' => 'Taomlar'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->dish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Osh'],
            'price' => 20000,
            'sort_order' => 1,
            'is_available' => true,
        ]);
    }

    private function customerHeader(): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'start_param' => 'r'.$this->restaurant->id.'_t1',
            'user' => json_encode(['id' => 111222333, 'first_name' => 'Test', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    public function test_price_is_always_recomputed_from_the_server_never_from_the_client(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 3, 'price' => 1]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        $this->assertSame(60000.0, (float) $response->json('order.total_price'));
        $this->assertSame(20000.0, (float) $response->json('order.items.0.unit_price'));
    }

    public function test_discount_price_is_applied_when_live(): void
    {
        $this->dish->update([
            'discount_percent' => 50,
            'discount_ends_at' => now()->addMinutes(30),
            'discount_portions_total' => 5,
            'discount_portions_remaining' => 5,
        ]);

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 2]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        $this->assertSame(10000.0, (float) $response->json('order.items.0.unit_price')); // 20000 * 0.5
        $this->assertSame(20000.0, (float) $response->json('order.total_price'));
        $this->assertSame(3, $this->dish->fresh()->discount_portions_remaining);
    }

    public function test_discount_price_is_not_applied_once_expired(): void
    {
        $this->dish->update([
            'discount_percent' => 50,
            'discount_ends_at' => now()->subMinute(),
            'discount_portions_total' => 5,
            'discount_portions_remaining' => 5,
        ]);

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        $this->assertSame(20000.0, (float) $response->json('order.items.0.unit_price'));
    }

    public function test_ordering_more_than_remaining_discount_portions_is_rejected(): void
    {
        $this->dish->update([
            'discount_percent' => 50,
            'discount_ends_at' => now()->addMinutes(30),
            'discount_portions_total' => 2,
            'discount_portions_remaining' => 2,
        ]);

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 3]],
        ], $this->customerHeader());

        $response->assertStatus(422);
        $this->assertSame(2, $this->dish->fresh()->discount_portions_remaining);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_new_orders_default_to_an_unpaid_payment_status(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        $this->assertSame(Order::PAYMENT_STATUS_UNPAID, $response->json('order.payment_status'));
    }

    public function test_staff_from_another_restaurant_cannot_update_order_status(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => TelegramUser::create(['telegram_id' => 1, 'first_name' => 'X'])->id,
            'status' => Order::STATUS_PENDING,
            'total_price' => 20000,
        ]);

        $otherRestaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Boshqa'],
            'latitude' => 40.0,
            'longitude' => 65.0,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        Staff::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Foreign',
            'role' => Staff::ROLE_CASHIER,
            'telegram_id' => 700000099,
            'is_active' => true,
        ]);

        $foreignAuth = ['X-Telegram-Init-Data' => app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => 700000099, 'first_name' => 'Staff', 'language_code' => 'uz']),
        ])];

        $response = $this->patchJson("/api/staff/orders/{$order->id}/status", ['status' => 'confirmed'], $foreignAuth);

        $response->assertStatus(404);
    }

    public function test_staff_cannot_skip_directly_from_pending_to_served(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => TelegramUser::create(['telegram_id' => 1, 'first_name' => 'X'])->id,
            'status' => Order::STATUS_PENDING,
            'total_price' => 20000,
        ]);

        Staff::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cashier',
            'role' => Staff::ROLE_CASHIER,
            'telegram_id' => 700000001,
            'is_active' => true,
        ]);

        $auth = ['X-Telegram-Init-Data' => app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => 700000001, 'first_name' => 'Staff', 'language_code' => 'uz']),
        ])];

        $response = $this->patchJson("/api/staff/orders/{$order->id}/status", ['status' => 'served'], $auth);

        $response->assertStatus(422);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_staff_cannot_transition_a_terminal_paid_order(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => TelegramUser::create(['telegram_id' => 1, 'first_name' => 'X'])->id,
            'status' => Order::STATUS_PAID,
            'total_price' => 20000,
        ]);

        Staff::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cashier',
            'role' => Staff::ROLE_CASHIER,
            'telegram_id' => 700000002,
            'is_active' => true,
        ]);

        $auth = ['X-Telegram-Init-Data' => app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => 700000002, 'first_name' => 'Staff', 'language_code' => 'uz']),
        ])];

        $response = $this->patchJson("/api/staff/orders/{$order->id}/status", ['status' => 'pending'], $auth);

        $response->assertStatus(422);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_order_creation_notifies_the_kitchen_chat_when_configured(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]])]);

        $this->restaurant->update(['kitchen_chat_id' => -100999]);

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 2]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        $orderId = $response->json('order.id');

        Http::assertSent(function ($request) use ($orderId) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($request->body(), 'chat_id=-100999')
                && str_contains($body, 'Osh')
                && str_contains($body, "order_ready:{$orderId}");
        });
    }

    public function test_a_telegram_api_level_failure_is_logged_but_order_creation_still_succeeds(): void
    {
        // Telegram can return HTTP 200 with `ok: false` for a well-formed but
        // rejected call (e.g. "chat not found" because the bot was never
        // added to that group) — Http::post() does not throw for this, so
        // it must be logged explicitly or it fails completely silently.
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'])]);
        Log::spy();

        $this->restaurant->update(['kitchen_chat_id' => -100999]);

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'telegram.api_call_failed'
                && $context['description'] === 'Bad Request: chat not found');
    }

    public function test_order_creation_does_not_call_telegram_when_kitchen_chat_id_is_not_set(): void
    {
        Http::fake();

        $response = $this->postJson('/api/orders', [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]],
        ], $this->customerHeader());

        $response->assertStatus(201);
        Http::assertNothingSent();
    }

    public function test_a_customer_cannot_view_another_customers_order(): void
    {
        $otherUser = TelegramUser::create(['telegram_id' => 999999998, 'first_name' => 'Other']);
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => $otherUser->id,
            'status' => Order::STATUS_PENDING,
            'total_price' => 20000,
        ]);

        $response = $this->getJson("/api/orders/{$order->id}", $this->customerHeader());

        $response->assertStatus(404);
    }
}
