<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidQrSessionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\TelegramUser;
use App\Services\DailyStatsService;
use App\Services\TableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const REVIEWABLE_STATUSES = [Order::STATUS_SERVED, Order::STATUS_PAID];

    public function __construct(
        private readonly TableResolver $tableResolver,
        private readonly DailyStatsService $dailyStats,
    ) {}

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
     * Leave a review for the restaurant encoded in the QR-signed
     * start_param. No order is required — a review is just an opinion,
     * not proof of purchase. `order_id` is optional: when given (and it
     * belongs to the caller, is served/paid, and not already reviewed)
     * the review is linked to it and counts as "verified"; otherwise the
     * review is still created, just unlinked.
     */
    public function store(Request $request): JsonResponse
    {
        $initData = $request->attributes->get('telegramInitData', []);

        try {
            ['restaurant' => $restaurant] = $this->tableResolver->resolve($initData['start_param'] ?? null);
        } catch (InvalidQrSessionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        /** @var TelegramUser $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = null;

        if (! empty($validated['order_id'])) {
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
        }

        $review = Review::create([
            'restaurant_id' => $order->restaurant_id ?? $restaurant->id,
            'order_id' => $order?->id,
            'telegram_user_id' => $telegramUser->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $this->dailyStats->recordReview($review);

        return response()->json(['review' => $review], 201);
    }
}
