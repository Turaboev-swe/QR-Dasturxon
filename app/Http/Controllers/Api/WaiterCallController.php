<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidQrSessionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\TelegramUser;
use App\Models\WaiterCall;
use App\Services\TableResolver;
use App\Services\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WaiterCallController extends Controller
{
    public function __construct(
        private readonly TableResolver $tableResolver,
        private readonly TelegramNotifier $telegramNotifier,
    ) {}

    /**
     * List the authenticated Telegram user's own waiter calls.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        $calls = WaiterCall::query()
            ->where('telegram_user_id', $telegramUser->id)
            ->latest()
            ->get();

        return response()->json(['waiter_calls' => $calls]);
    }

    /**
     * Call a waiter to the table encoded in the QR-signed start_param.
     * Refuses a new call while one for the same table is still open,
     * so a table can't spam the staff with duplicate calls.
     */
    public function store(Request $request): JsonResponse
    {
        $initData = $request->attributes->get('telegramInitData', []);

        try {
            ['restaurant' => $restaurant, 'table' => $table] = $this->tableResolver->resolve(
                $initData['start_param'] ?? null,
            );
        } catch (InvalidQrSessionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(WaiterCall::TYPES)],
        ]);

        $type = $validated['type'] ?? WaiterCall::TYPE_WAITER;

        $hasOpenCall = WaiterCall::query()
            ->where('restaurant_table_id', $table->id)
            ->where('type', $type)
            ->whereIn('status', [WaiterCall::STATUS_PENDING, WaiterCall::STATUS_ACKNOWLEDGED])
            ->exists();

        if ($hasOpenCall) {
            $message = $type === WaiterCall::TYPE_BILL
                ? 'Ushbu stol uchun hisob so\'rovi allaqachon yuborilgan.'
                : 'Ushbu stol uchun ofitsiant chaqiruvi allaqachon yuborilgan.';

            return response()->json(['message' => $message], 422);
        }

        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        // Once a waiter has picked up a call for this table (see
        // TelegramWebhookController::handleCallDone), every further
        // "waiter" call is routed straight to them as a plain reminder
        // instead of re-broadcasting to the whole group — cleared back to
        // nobody the moment a new order starts (OrderController::store).
        // Bill requests are never affected by this — they always broadcast.
        $isReminderToAssignedWaiter = $type === WaiterCall::TYPE_WAITER && $table->assigned_waiter_telegram_id !== null;

        $call = WaiterCall::create([
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $table->id,
            'telegram_user_id' => $telegramUser->id,
            'status' => $isReminderToAssignedWaiter ? WaiterCall::STATUS_RESOLVED : WaiterCall::STATUS_PENDING,
            'type' => $type,
            'handled_by_telegram_id' => $isReminderToAssignedWaiter ? $table->assigned_waiter_telegram_id : null,
            'handled_by_name' => $isReminderToAssignedWaiter ? $table->assigned_waiter_name : null,
        ]);

        if ($restaurant->waiter_chat_id) {
            $this->notifyWaiter($restaurant, $table, $call, $isReminderToAssignedWaiter);
        }

        return response()->json(['waiter_call' => $call], 201);
    }

    /**
     * Best-effort push to the restaurant's waiter chat — a missing chat id
     * is the normal, silent case; any other failure is logged and
     * swallowed, the call is already committed.
     */
    private function notifyWaiter(Restaurant $restaurant, RestaurantTable $table, WaiterCall $call, bool $isReminderToAssignedWaiter): void
    {
        $tableName = $table->name ?: "Stol {$table->code}";
        $replyMarkup = null;

        if ($call->type === WaiterCall::TYPE_BILL) {
            // Sums every currently-unpaid order for this table. Since nothing
            // sets `payment_status` to `paid` yet (a separate, later task —
            // see CLAUDE.md), this will over-count a table's bill with any
            // of its past, already-settled visits until that lands.
            $total = Order::query()
                ->where('restaurant_table_id', $table->id)
                ->where('payment_status', Order::PAYMENT_STATUS_UNPAID)
                ->with('items')
                ->get()
                ->sum(fn (Order $order) => $order->calculateTotal());

            $totalLine = 'Jami: '.number_format($total, 0, '.', ' ').' so\'m';

            // A bill request always broadcasts+button regardless of
            // assignment (anyone can bring the check) — but if a waiter is
            // already assigned to this table, the message calls them out by
            // name so they know it's theirs to handle. This never writes to
            // assigned_waiter_* — it only reads the existing assignment.
            $text = $table->assigned_waiter_telegram_id !== null
                ? "{$table->assigned_waiter_name}, siz xizmat ko'rsatgan Stol {$table->code} hisob so'ramoqda\n{$totalLine}"
                : "🧾 Hisob so'raldi — {$tableName}\n{$totalLine}";

            $replyMarkup = ['inline_keyboard' => [[
                ['text' => '✅ Bajarildi', 'callback_data' => "call_done:{$call->id}"],
            ]]];
        } elseif ($isReminderToAssignedWaiter) {
            $text = "🔔 {$table->assigned_waiter_name}, sizni Stol {$table->code}dan yana chaqirishmoqda";
        } else {
            $text = "🔔 Ofitsiant chaqirildi — {$tableName}";
            $replyMarkup = ['inline_keyboard' => [[
                ['text' => '✅ Bajarildi', 'callback_data' => "call_done:{$call->id}"],
            ]]];
        }

        try {
            $this->telegramNotifier->sendMessage($restaurant->waiter_chat_id, $text, $replyMarkup);
        } catch (\Throwable $e) {
            Log::warning('telegram.waiter_notify_failed', ['call_id' => $call->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * List open (pending/acknowledged) waiter calls for the staff member's own restaurant.
     */
    public function staffIndex(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        $calls = WaiterCall::query()
            ->where('restaurant_id', $staff->restaurant_id)
            ->whereIn('status', [WaiterCall::STATUS_PENDING, WaiterCall::STATUS_ACKNOWLEDGED])
            ->with('table')
            ->latest()
            ->get();

        return response()->json(['waiter_calls' => $calls]);
    }

    /**
     * Update a waiter call's status (staff only, scoped to their own restaurant).
     */
    public function updateStatus(Request $request, WaiterCall $waiterCall): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        if ($waiterCall->restaurant_id !== $staff->restaurant_id) {
            return response()->json(['message' => 'Chaqiruv topilmadi.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(WaiterCall::STATUSES)],
        ]);

        if (! $waiterCall->canTransitionTo($validated['status'])) {
            return response()->json([
                'message' => "Chaqiruv holatini '{$waiterCall->status}' dan '{$validated['status']}' ga o'zgartirib bo'lmaydi.",
            ], 422);
        }

        $waiterCall->update(['status' => $validated['status']]);

        return response()->json(['waiter_call' => $waiterCall->fresh()]);
    }
}
