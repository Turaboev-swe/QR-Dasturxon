<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TelegramUser;
use App\Models\WaiterCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private Restaurant $restaurant;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.webhook_secret' => self::SECRET]);

        $this->restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
            'waiter_chat_id' => -100888,
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => '5',
            'name' => 'Stol 5',
            'is_active' => true,
        ]);
    }

    private function callbackPayload(string $data, int $chatId = -100999, int $messageId = 42, string $text = 'Original message', ?array $from = null): array
    {
        return [
            'update_id' => 1,
            'callback_query' => array_filter([
                'id' => 'cb1',
                'data' => $data,
                'from' => $from,
                'message' => [
                    'message_id' => $messageId,
                    'text' => $text,
                    'chat' => ['id' => $chatId],
                ],
            ], fn ($value) => $value !== null),
        ];
    }

    public function test_webhook_rejects_a_missing_secret_token(): void
    {
        Http::fake();

        $response = $this->postJson('/api/telegram/webhook', $this->callbackPayload('order_ready:1'));

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_webhook_rejects_a_wrong_secret_token(): void
    {
        Http::fake();

        $response = $this->postJson('/api/telegram/webhook', $this->callbackPayload('order_ready:1'), [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_webhook_ignores_non_callback_updates_without_erroring(): void
    {
        Http::fake();

        $response = $this->postJson('/api/telegram/webhook', ['update_id' => 1, 'message' => ['text' => 'hi']], [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        Http::assertNothingSent();
    }

    public function test_order_ready_callback_advances_status_and_edits_the_message(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $telegramUser = TelegramUser::create(['telegram_id' => 1, 'first_name' => 'X']);
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => $telegramUser->id,
            'status' => Order::STATUS_PENDING,
            'total_price' => 20000,
        ]);

        $response = $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload("order_ready:{$order->id}", chatId: -100999, messageId: 42, text: 'Yangi buyurtma'),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/editMessageText')
                && str_contains($request->body(), 'chat_id=-100999')
                && str_contains($request->body(), 'message_id=42')
                && str_contains($body, 'Bajarildi');
        });

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/sendMessage')
                && str_contains($request->body(), 'chat_id=-100888')
                && str_contains($body, 'Stol 5 buyurtmasi tayyor');
        });
    }

    public function test_order_ready_callback_is_a_no_op_for_an_unknown_order_id(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload('order_ready:999999'),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
        Http::assertNothingSent();
    }

    public function test_call_done_callback_resolves_the_waiter_call(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => null,
            'status' => WaiterCall::STATUS_PENDING,
            'type' => WaiterCall::TYPE_WAITER,
        ]);

        $response = $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload("call_done:{$call->id}", chatId: -100888, messageId: 7, text: 'Ofitsiant chaqirildi'),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertSame(WaiterCall::STATUS_RESOLVED, $call->fresh()->status);

        Http::assertSent(function ($request) {
            $body = urldecode($request->body());

            return str_contains($request->url(), '/editMessageText')
                && str_contains($request->body(), 'chat_id=-100888')
                && str_contains($request->body(), 'message_id=7')
                && str_contains($body, 'Bajarildi');
        });
    }

    public function test_call_done_is_idempotent_once_already_resolved(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => null,
            'status' => WaiterCall::STATUS_RESOLVED,
            'type' => WaiterCall::TYPE_WAITER,
        ]);

        $response = $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload("call_done:{$call->id}"),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertSame(WaiterCall::STATUS_RESOLVED, $call->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_call_done_assigns_the_table_to_the_waiter_who_pressed_the_button(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => null,
            'status' => WaiterCall::STATUS_PENDING,
            'type' => WaiterCall::TYPE_WAITER,
        ]);

        $response = $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload(
                "call_done:{$call->id}",
                from: ['id' => 555000111, 'username' => 'aziz_waiter', 'first_name' => 'Aziz'],
            ),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);

        $this->assertSame(555000111, $call->fresh()->handled_by_telegram_id);
        $this->assertSame('@aziz_waiter', $call->fresh()->handled_by_name);

        $freshTable = $this->table->fresh();
        $this->assertSame(555000111, $freshTable->assigned_waiter_telegram_id);
        $this->assertSame('@aziz_waiter', $freshTable->assigned_waiter_name);
    }

    public function test_call_done_falls_back_to_first_name_when_the_waiter_has_no_username(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => null,
            'status' => WaiterCall::STATUS_PENDING,
            'type' => WaiterCall::TYPE_WAITER,
        ]);

        $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload("call_done:{$call->id}", from: ['id' => 555000222, 'first_name' => 'Malika']),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $this->assertSame('Malika', $this->table->fresh()->assigned_waiter_name);
    }

    public function test_call_done_does_not_assign_the_table_for_a_bill_call(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'telegram_user_id' => null,
            'status' => WaiterCall::STATUS_PENDING,
            'type' => WaiterCall::TYPE_BILL,
        ]);

        $this->postJson(
            '/api/telegram/webhook',
            $this->callbackPayload("call_done:{$call->id}", from: ['id' => 555000111, 'username' => 'aziz_waiter']),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $this->assertNull($this->table->fresh()->assigned_waiter_telegram_id);
    }
}
