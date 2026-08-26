<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WaiterCall;
use App\Services\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramNotifier $telegramNotifier,
    ) {}

    /**
     * Receives Telegram Bot API webhook updates (inline button taps from the
     * kitchen/waiter group chats). Deliberately outside `telegram.auth` —
     * that middleware verifies Mini App `initData`, but this is a
     * server-to-server push from Telegram itself and is authenticated by
     * the `X-Telegram-Bot-Api-Secret-Token` header instead.
     */
    public function handle(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('services.telegram.webhook_secret');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $callback = $request->input('callback_query');

        if (! is_array($callback) || ! isset($callback['data'], $callback['message']['chat']['id'], $callback['message']['message_id'])) {
            // Telegram sends other update types too (plain messages, etc.);
            // this endpoint only acts on callback_query, everything else is
            // acknowledged as a no-op so Telegram doesn't retry the webhook.
            return response()->json(['ok' => true]);
        }

        [$action, $id] = array_pad(explode(':', (string) $callback['data'], 2), 2, null);
        $chatId = $callback['message']['chat']['id'];
        $messageId = (int) $callback['message']['message_id'];
        $originalText = (string) ($callback['message']['text'] ?? '');
        $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];

        if ($action === 'order_ready' && $id !== null) {
            $this->handleOrderReady((int) $id, $chatId, $messageId, $originalText);
        } elseif ($action === 'call_done' && $id !== null) {
            $this->handleCallDone((int) $id, $chatId, $messageId, $originalText, $from);
        }

        return response()->json(['ok' => true]);
    }

    private function handleOrderReady(int $orderId, int|string $chatId, int $messageId, string $originalText): void
    {
        $order = Order::with(['table', 'restaurant'])->find($orderId);

        if (! $order) {
            return;
        }

        // A kitchen "tayyor" tap is an authoritative external "food is
        // ready" signal — it fast-forwards past whatever intermediate stage
        // the order is sitting in (pending/confirmed/preparing) rather than
        // requiring the same strict single-step canTransitionTo() the staff
        // panel enforces. No-op if the order already reached/passed `ready`.
        $notYetReady = ! in_array($order->status, [
            Order::STATUS_READY, Order::STATUS_SERVED, Order::STATUS_PAID, Order::STATUS_CANCELLED,
        ], true);

        if ($notYetReady) {
            $order->update(['status' => Order::STATUS_READY]);
        }

        $this->markDone($chatId, $messageId, $originalText, '✅ Bajarildi');

        if ($order->table && $order->restaurant?->waiter_chat_id) {
            try {
                $this->telegramNotifier->sendMessage(
                    $order->restaurant->waiter_chat_id,
                    "✅ Stol {$order->table->code} buyurtmasi tayyor — olib boring.",
                );
            } catch (\Throwable $e) {
                Log::warning('telegram.order_ready_notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $from  The Telegram user who tapped the
     *                                      button — a group member, not
     *                                      necessarily a registered `Staff`.
     */
    private function handleCallDone(int $callId, int|string $chatId, int $messageId, string $originalText, array $from): void
    {
        // lockForUpdate() inside a transaction so two near-simultaneous
        // deliveries of the same callback (a Telegram webhook retry, or two
        // people tapping the button at once) can't both pass
        // canTransitionTo() before either write commits — the second
        // waiter blocks until the first transaction commits, then re-reads
        // the now-`resolved` status and no-ops instead of double-assigning
        // the table.
        $handlerName = null;

        $resolved = DB::transaction(function () use ($callId, $from, &$handlerName) {
            $call = WaiterCall::with('table')->lockForUpdate()->find($callId);

            if (! $call || ! $call->canTransitionTo(WaiterCall::STATUS_RESOLVED)) {
                return false;
            }

            $handlerTelegramId = $from['id'] ?? null;
            $handlerName = ! empty($from['username'])
                ? '@'.$from['username']
                : ($from['first_name'] ?? null);

            $call->update([
                'status' => WaiterCall::STATUS_RESOLVED,
                'handled_by_telegram_id' => $handlerTelegramId,
                'handled_by_name' => $handlerName,
            ]);

            // Only a resolved "waiter" call assigns the table — "bill"
            // calls never touch table assignment (see CLAUDE.md).
            if ($call->type === WaiterCall::TYPE_WAITER && $call->table && $handlerTelegramId !== null) {
                $call->table->update([
                    'assigned_waiter_telegram_id' => $handlerTelegramId,
                    'assigned_waiter_name' => $handlerName,
                ]);
            }

            return true;
        });

        if ($resolved) {
            $this->markDone($chatId, $messageId, $originalText, $handlerName ? "✅ {$handlerName} bordi" : '✅ Bajarildi');
        }
    }

    private function markDone(int|string $chatId, int $messageId, string $originalText, string $suffix): void
    {
        $text = trim($originalText) !== '' ? $originalText."\n\n{$suffix}" : $suffix;

        try {
            // `reply_markup` must be sent as an explicit empty keyboard, not
            // omitted — Telegram's editMessageText leaves the existing
            // inline keyboard untouched when the field is absent, it only
            // clears it when given `{"inline_keyboard":[]}`.
            $this->telegramNotifier->editMessageText($chatId, $messageId, $text, ['inline_keyboard' => []]);
        } catch (\Throwable $e) {
            Log::warning('telegram.edit_message_failed', ['chat_id' => $chatId, 'message_id' => $messageId, 'error' => $e->getMessage()]);
        }
    }
}
