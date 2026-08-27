<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidQrSessionException;
use App\Http\Controllers\Concerns\ResolvesLocale;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\TelegramUser;
use App\Services\TableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use ResolvesLocale;

    public function __construct(
        private readonly TableResolver $tableResolver,
    ) {}

    /**
     * Return the menu of the restaurant encoded in the QR start_param,
     * translated into the requested (or Telegram client) language.
     *
     * Without a valid start_param (Mini App opened via the plain ☰ menu
     * button instead of a QR/startapp link) the menu is still shown in a
     * read-only "demo" mode for the sole active restaurant, so the app
     * isn't a dead end — but table identity stays undetermined, so every
     * write endpoint (orders/reviews/waiter-calls) still requires a real
     * start_param and independently returns 422 without one.
     */
    public function index(Request $request): JsonResponse
    {
        $initData = $request->attributes->get('telegramInitData', []);
        $isDemo = false;

        try {
            ['restaurant' => $restaurant] = $this->tableResolver->resolve($initData['start_param'] ?? null);
        } catch (InvalidQrSessionException $e) {
            $restaurant = Restaurant::where('is_active', true)->orderBy('id')->first();

            if (! $restaurant) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $isDemo = true;
        }

        /** @var TelegramUser|null $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');
        $locale = $this->resolveLocale($request, $telegramUser);

        $restaurant->loadCount('reviews')->loadAvg('reviews', 'rating');

        $chef = $restaurant->chefs()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        $recentReviews = Review::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereNotNull('comment')
            ->with('telegramUser')
            ->latest()
            ->limit(6)
            ->get();

        $categories = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['dishes' => function ($query) {
                $query->where('is_available', true)->orderBy('sort_order');
            }])
            ->get();

        return response()->json([
            'language' => $locale,
            'demo' => $isDemo,
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->translate('name_translations', $locale),
                'is_verified' => $restaurant->is_verified,
                'badge_text' => $restaurant->badge_text,
                'rating' => $restaurant->reviews_count > 0 ? round((float) $restaurant->reviews_avg_rating, 1) : null,
                'reviews_count' => $restaurant->reviews_count,
                'chef' => $chef ? [
                    'name' => $chef->name,
                    'title' => $chef->title,
                    'experience_years' => $chef->experience_years,
                    'specialty' => $chef->specialty,
                    'tier_badge' => $chef->tier_badge,
                    'photo_path' => $chef->photo_path,
                ] : null,
                'recent_reviews' => $recentReviews->map(fn (Review $review) => [
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'name' => $review->telegramUser->first_name,
                    'created_at' => $review->created_at,
                    'verified' => $review->order_id !== null,
                ]),
            ],
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->translate('name_translations', $locale),
                'dishes' => $category->dishes->map(fn (Dish $dish) => [
                    'id' => $dish->id,
                    'name' => $dish->translate('name_translations', $locale),
                    'description' => $dish->translate('description_translations', $locale),
                    'ingredients' => $dish->translate('ingredients_translations', $locale),
                    'allergens' => $dish->allergens ?? [],
                    'price' => $dish->price,
                    'image_path' => $dish->image_path,
                    'discount' => $dish->hasLiveDiscount() ? [
                        'percent' => $dish->discount_percent,
                        'price' => $dish->effectivePrice(),
                        'original_price' => (float) $dish->price,
                        'ends_at' => $dish->discount_ends_at,
                        'portions_remaining' => $dish->discount_portions_remaining,
                        'portions_total' => $dish->discount_portions_total,
                    ] : null,
                    'taste' => $dish->hasTasteProfile() ? [
                        'spicy' => $dish->taste_spicy,
                        'sweet' => $dish->taste_sweet,
                        'salty' => $dish->taste_salty,
                    ] : null,
                ]),
            ]),
        ]);
    }
}
