<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\TelegramUser;
use App\Models\WaiterCall;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
