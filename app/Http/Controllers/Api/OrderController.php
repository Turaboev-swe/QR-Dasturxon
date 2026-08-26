<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DishUnavailableException;
use App\Exceptions\InvalidQrSessionException;
use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\TelegramUser;
use App\Services\TableResolver;
use App\Services\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly TableResolver $tableResolver,
        private readonly TelegramNotifier $telegramNotifier,
    ) {}

    /**
     * List the authenticated Telegram user's own orders.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        $orders = Order::query()
            ->where('telegram_user_id', $telegramUser->id)
            ->with(['items.dish', 'review'])
            ->latest()
            ->get();

        return response()->json(['orders' => $orders]);
    }

    /**
     * Show a single order belonging to the authenticated Telegram user.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        if ($order->telegram_user_id !== $telegramUser->id) {
            return response()->json(['message' => 'Buyurtma topilmadi.'], 404);
        }

        return response()->json(['order' => $order->load(['items.dish', 'review'])]);
    }

    /**
     * List today's non-cancelled orders for the staff member's own restaurant.
     */
    public function staffIndex(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        $orders = Order::query()
            ->where('restaurant_id', $staff->restaurant_id)
            ->whereDate('created_at', today())
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->with(['items.dish', 'table'])
            ->latest()
            ->get();

        return response()->json(['orders' => $orders]);
    }

    /**
     * Create an order. The table/restaurant come only from the QR-signed
     * start_param, and every price is recomputed from the `dishes` table —
     * prices submitted by the client are never trusted.
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $dishIds = array_column($validated['items'], 'dish_id');

        // Fast-fail before opening a transaction/lock: catches the common case
        // (unavailable dish, wrong restaurant) without paying for row locking.
        $availableCount = Dish::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_available', true)
            ->whereIn('id', $dishIds)
            ->count();

        if ($availableCount !== count($dishIds)) {
            return response()->json([
                'message' => 'Ba\'zi taomlar mavjud emas yoki bu restoranga tegishli emas.',
            ], 422);
        }

        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        try {
            $order = DB::transaction(function () use ($validated, $dishIds, $restaurant, $table, $telegramUser) {
                // Authoritative re-fetch under a row lock: guards against a
                // discount's portions running out between two concurrent orders.
                $dishes = Dish::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_available', true)
                    ->whereIn('id', $dishIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($dishes->count() !== count($dishIds)) {
                    throw new DishUnavailableException('Ba\'zi taomlar mavjud emas yoki bu restoranga tegishli emas.');
                }

                $order = Order::create([
                    'restaurant_id' => $restaurant->id,
                    'restaurant_table_id' => $table->id,
                    'telegram_user_id' => $telegramUser->id,
                    'status' => Order::STATUS_PENDING,
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                    'total_price' => 0,
                    'comment' => $validated['comment'] ?? null,
                ]);

                $totalPrice = 0.0;

                foreach ($validated['items'] as $item) {
                    $dish = $dishes[$item['dish_id']];
                    $quantity = $item['quantity'];

                    if ($dish->hasLiveDiscount()) {
                        if ($dish->discount_portions_remaining < $quantity) {
                            throw new DishUnavailableException(
                                "\"{$dish->name_translations['uz']}\" taomining chegirmali porsiyalari yetarli emas.",
                            );
                        }

                        $unitPrice = $dish->effectivePrice();
                        $dish->decrement('discount_portions_remaining', $quantity);
                    } else {
                        $unitPrice = (float) $dish->price;
                    }

                    $lineTotal = round($unitPrice * $quantity, 2);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'dish_id' => $dish->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $lineTotal,
                    ]);

                    $totalPrice = round($totalPrice + $lineTotal, 2);
                }

                $order->update(['total_price' => $totalPrice]);

                return $order;
            });
        } catch (DishUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load('items.dish');

        // A new order means a new dining session at this table — the
        // previously-assigned waiter (see WaiterCallController::store) no
        // longer applies, so the next "waiter" call broadcasts to the whole
        // group again instead of pinging whoever handled the last visit.
        if ($table->assigned_waiter_telegram_id !== null) {
            $table->update(['assigned_waiter_telegram_id' => null, 'assigned_waiter_name' => null]);
        }

        if ($restaurant->kitchen_chat_id) {
            $this->notifyKitchen($restaurant, $table, $order);
        }

        return response()->json(['order' => $order], 201);
    }

    /**
     * Best-effort push to the restaurant's kitchen chat. A missing chat id
     * is the normal, silent case (checked by the caller); any other failure
     * (network, bot removed from the group, etc.) is logged and swallowed —
     * the order is already committed and must not be undone by a Telegram
     * hiccup.
     */
    private function notifyKitchen(Restaurant $restaurant, RestaurantTable $table, Order $order): void
    {
        $itemLines = $order->items
            ->map(fn (OrderItem $item) => "• {$item->dish->name_translations['uz']} × {$item->quantity}")
            ->implode("\n");

        $tableName = $table->name ?: "Stol {$table->code}";
        $text = "🍽 Yangi buyurtma — {$tableName}\n\n{$itemLines}\n\nJami: ".number_format($order->total_price, 0, '.', ' ')." so'm";

        try {
            $this->telegramNotifier->sendMessage($restaurant->kitchen_chat_id, $text, [
                'inline_keyboard' => [[
                    ['text' => '✅ Tayyor', 'callback_data' => "order_ready:{$order->id}"],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('telegram.kitchen_notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update an order's status (cashier/admin staff only, scoped to their own restaurant).
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        if (! $staff->canManageOrders()) {
            return response()->json(['message' => 'Ruxsat yo\'q.'], 403);
        }

        if ($order->restaurant_id !== $staff->restaurant_id) {
            return response()->json(['message' => 'Buyurtma topilmadi.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        if (! $order->canTransitionTo($validated['status'])) {
            return response()->json([
                'message' => "Buyurtma holatini '{$order->status}' dan '{$validated['status']}' ga o'zgartirib bo'lmaydi.",
            ], 422);
        }

        $order->update(['status' => $validated['status']]);

        return response()->json(['order' => $order->fresh('items.dish')]);
    }
}
