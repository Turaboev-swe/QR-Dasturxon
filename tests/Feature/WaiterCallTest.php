<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\TelegramUser;
use App\Models\WaiterCall;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WaiterCallTest extends TestCase
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
            'name' => 'Stol 1',
            'is_active' => true,
        ]);
    }

    private function customerHeaderFor(int $telegramId, string $startParam): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'start_param' => $startParam,
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Test', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    private function createCashier(int $telegramId = 700000001): array
    {
        $cashier = Staff::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cashier',
            'role' => Staff::ROLE_CASHIER,
            'telegram_id' => $telegramId,
            'is_active' => true,
        ]);

        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Staff', 'language_code' => 'uz']),
        ]);

        return [$cashier, ['X-Telegram-Init-Data' => $initData]];
    }

    public function test_customer_can_call_a_waiter_for_their_table(): void
    {
        $response = $this->postJson(
            '/api/waiter-calls',
            [],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201)
            ->assertJson([
                'waiter_call' => [
                    'restaurant_id' => $this->restaurant->id,
                    'restaurant_table_id' => $this->table->id,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseCount('waiter_calls', 1);
    }

    public function test_cannot_call_a_waiter_twice_while_one_is_still_open(): void
    {
        $header = $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1');

        $this->postJson('/api/waiter-calls', [], $header)->assertStatus(201);
        $response = $this->postJson('/api/waiter-calls', [], $header);

        $response->assertStatus(422);
        $this->assertDatabaseCount('waiter_calls', 1);
    }

    public function test_invalid_start_param_returns_422(): void
    {
        $response = $this->postJson(
            '/api/waiter-calls',
            [],
            $this->customerHeaderFor(111222333, 'r999999_t1'),
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('waiter_calls', 0);
    }

    public function test_staff_can_see_and_resolve_an_open_call(): void
    {
        [, $auth] = $this->createCashier();

        $this->postJson('/api/waiter-calls', [], $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'))
            ->assertStatus(201);

        $list = $this->getJson('/api/staff/waiter-calls', $auth);
        $list->assertStatus(200)->assertJsonCount(1, 'waiter_calls');

        $callId = $list->json('waiter_calls.0.id');

        $ack = $this->patchJson("/api/staff/waiter-calls/{$callId}/status", ['status' => 'acknowledged'], $auth);
        $ack->assertStatus(200)->assertJsonPath('waiter_call.status', 'acknowledged');

        $resolve = $this->patchJson("/api/staff/waiter-calls/{$callId}/status", ['status' => 'resolved'], $auth);
        $resolve->assertStatus(200)->assertJsonPath('waiter_call.status', 'resolved');
    }

    public function test_staff_cannot_skip_from_pending_to_resolved_then_back(): void
    {
        [, $auth] = $this->createCashier();

        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => null,
            'status' => WaiterCall::STATUS_RESOLVED,
        ]);

        $response = $this->patchJson("/api/staff/waiter-calls/{$call->id}/status", ['status' => 'pending'], $auth);

        $response->assertStatus(422);
    }

    public function test_a_bill_request_can_be_open_alongside_an_open_waiter_call_for_the_same_table(): void
    {
        $header = $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1');

        $this->postJson('/api/waiter-calls', ['type' => 'waiter'], $header)->assertStatus(201);
        $this->postJson('/api/waiter-calls', ['type' => 'bill'], $header)->assertStatus(201);

        $this->assertDatabaseCount('waiter_calls', 2);
    }

    public function test_a_second_bill_request_of_the_same_type_still_gets_rejected(): void
    {
        $header = $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1');

        $this->postJson('/api/waiter-calls', ['type' => 'bill'], $header)->assertStatus(201);
        $response = $this->postJson('/api/waiter-calls', ['type' => 'bill'], $header);

        $response->assertStatus(422);
        $this->assertDatabaseCount('waiter_calls', 1);
    }

    public function test_staff_from_another_restaurant_cannot_touch_the_call(): void
    {
        $this->postJson('/api/waiter-calls', [], $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'))
            ->assertStatus(201);

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

        $callId = WaiterCall::first()->id;

        $response = $this->patchJson("/api/staff/waiter-calls/{$callId}/status", ['status' => 'acknowledged'], $foreignAuth);

        $response->assertStatus(404);
    }

    public function test_waiter_call_notifies_the_waiter_chat_when_configured(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]])]);

        $this->restaurant->update(['waiter_chat_id' => -100888]);

        $response = $this->postJson(
            '/api/waiter-calls',
            ['type' => 'waiter'],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201);
        $callId = $response->json('waiter_call.id');

        Http::assertSent(function ($request) use ($callId) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($request->body(), 'chat_id=-100888')
                && str_contains($body, 'Ofitsiant chaqirildi')
                && str_contains($body, "call_done:{$callId}");
        });
    }

    public function test_bill_request_notifies_the_waiter_chat_with_the_computed_total(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]])]);

        $this->restaurant->update(['waiter_chat_id' => -100888]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_translations' => ['uz' => 'Taomlar'],
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $dish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Osh'],
            'price' => 25000,
            'sort_order' => 1,
            'is_available' => true,
        ]);
        $telegramUser = TelegramUser::create(['telegram_id' => 111222333, 'first_name' => 'Test']);
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => $telegramUser->id,
            'status' => Order::STATUS_PENDING,
            'total_price' => 50000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'total_price' => 50000,
        ]);

        $response = $this->postJson(
            '/api/waiter-calls',
            ['type' => 'bill'],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201);

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($body, 'Hisob so\'raldi')
                && str_contains($body, '50 000');
        });
    }

    public function test_waiter_call_does_not_call_telegram_when_waiter_chat_id_is_not_set(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/waiter-calls',
            [],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201);
        Http::assertNothingSent();
    }

    public function test_a_second_waiter_call_to_an_assigned_table_pings_that_waiter_without_a_button(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->restaurant->update(['waiter_chat_id' => -100888]);
        $this->table->update([
            'assigned_waiter_telegram_id' => 555000111,
            'assigned_waiter_name' => '@aziz_waiter',
        ]);

        $response = $this->postJson(
            '/api/waiter-calls',
            ['type' => 'waiter'],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201);
        $response->assertJsonPath('waiter_call.status', WaiterCall::STATUS_RESOLVED);
        $this->assertSame(555000111, $response->json('waiter_call.handled_by_telegram_id'));
        $this->assertSame('@aziz_waiter', $response->json('waiter_call.handled_by_name'));

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($body, '@aziz_waiter, sizni Stol 1dan yana chaqirishmoqda')
                && ! str_contains($request->body(), 'reply_markup')
                && ! str_contains($body, 'call_done:');
        });
    }

    public function test_placing_a_new_order_clears_the_assigned_waiter_and_the_next_call_broadcasts_again(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->restaurant->update(['waiter_chat_id' => -100888, 'kitchen_chat_id' => -100999]);
        $this->table->update([
            'assigned_waiter_telegram_id' => 555000111,
            'assigned_waiter_name' => '@aziz_waiter',
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_translations' => ['uz' => 'Taomlar'],
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $dish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Osh'],
            'price' => 20000,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        $this->postJson('/api/orders', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ], $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'))->assertStatus(201);

        $this->assertNull($this->table->fresh()->assigned_waiter_telegram_id);
        $this->assertNull($this->table->fresh()->assigned_waiter_name);

        $response = $this->postJson(
            '/api/waiter-calls',
            ['type' => 'waiter'],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201);
        $response->assertJsonPath('waiter_call.status', WaiterCall::STATUS_PENDING);

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($body, 'Ofitsiant chaqirildi')
                && str_contains($body, 'call_done:');
        });
    }

    public function test_a_bill_request_always_broadcasts_with_a_button_regardless_of_assigned_waiter(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->restaurant->update(['waiter_chat_id' => -100888]);
        $this->table->update([
            'assigned_waiter_telegram_id' => 555000111,
            'assigned_waiter_name' => '@aziz_waiter',
        ]);

        $response = $this->postJson(
            '/api/waiter-calls',
            ['type' => 'bill'],
            $this->customerHeaderFor(111222333, 'r'.$this->restaurant->id.'_t1'),
        );

        $response->assertStatus(201);
        $response->assertJsonPath('waiter_call.status', WaiterCall::STATUS_PENDING);

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($body, "Hisob so'raldi")
                && str_contains($body, 'call_done:');
        });
    }
}
