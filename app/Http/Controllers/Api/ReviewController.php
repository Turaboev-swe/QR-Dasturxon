<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const REVIEWABLE_STATUSES = [Order::STATUS_SERVED, Order::STATUS_PAID];

    /**
     * List the authenticated Telegram user's own reviews.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        $reviews = Review::query()
            ->where('telegram_user_id', $telegramUser->id)
            ->latest()
            ->get();

        return response()->json(['reviews' => $reviews]);
    }

    /**
     * Leave a review for one of the authenticated user's own completed orders.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::query()
            ->where('id', $validated['order_id'])
            ->where('telegram_user_id', $telegramUser->id)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Buyurtma topilmadi.'], 404);
        }

        if (! in_array($order->status, self::REVIEWABLE_STATUSES, true)) {
            return response()->json([
                'message' => 'Sharh faqat yakunlangan buyurtma uchun qoldirilishi mumkin.',
            ], 422);
        }

        if (Review::where('order_id', $order->id)->exists()) {
            return response()->json(['message' => 'Bu buyurtma uchun sharh allaqachon qoldirilgan.'], 422);
        }

        $review = Review::create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'telegram_user_id' => $telegramUser->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json(['review' => $review], 201);
    }
}
