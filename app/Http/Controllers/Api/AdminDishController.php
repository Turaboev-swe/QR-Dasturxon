<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDishController extends Controller
{
    /**
     * List every dish (available or not) for the admin's own restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        $dishes = Dish::query()
            ->where('restaurant_id', $staff->restaurant_id)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'dishes' => $dishes->map(fn (Dish $dish) => [
                'id' => $dish->id,
                'name' => $dish->translate('name_translations', 'uz'),
                'price' => $dish->price,
                'is_available' => $dish->is_available,
                'discount_live' => $dish->hasLiveDiscount(),
                'discount_percent' => $dish->discount_percent,
                'discount_ends_at' => $dish->discount_ends_at,
                'discount_portions_total' => $dish->discount_portions_total,
                'discount_portions_remaining' => $dish->discount_portions_remaining,
            ]),
        ]);
    }

    /**
     * Toggle a dish's availability (admin only, scoped to their own restaurant).
     */
    public function toggleAvailability(Request $request, Dish $dish): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        if ($dish->restaurant_id !== $staff->restaurant_id) {
            return response()->json(['message' => 'Taom topilmadi.'], 404);
        }

        $dish->update(['is_available' => ! $dish->is_available]);

        return response()->json(['dish' => $dish->fresh()]);
    }

    /**
     * Set a flash discount on one dish. Only one dish per restaurant can have
     * an active deal at a time — setting a new one clears any previous one.
     */
    public function setDiscount(Request $request, Dish $dish): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        if ($dish->restaurant_id !== $staff->restaurant_id) {
            return response()->json(['message' => 'Taom topilmadi.'], 404);
        }

        $validated = $request->validate([
            'percent' => ['required', 'integer', 'between:1,95'],
            'portions' => ['required', 'integer', 'min:1'],
            'minutes' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($staff, $dish, $validated) {
            Dish::where('restaurant_id', $staff->restaurant_id)
                ->where('id', '!=', $dish->id)
                ->update([
                    'discount_percent' => null,
                    'discount_ends_at' => null,
                    'discount_portions_total' => null,
                    'discount_portions_remaining' => null,
                ]);

            $dish->update([
                'discount_percent' => $validated['percent'],
                'discount_ends_at' => now()->addMinutes($validated['minutes']),
                'discount_portions_total' => $validated['portions'],
                'discount_portions_remaining' => $validated['portions'],
            ]);
        });

        return response()->json(['dish' => $dish->fresh()]);
    }

    /**
     * Clear whichever dish in the admin's restaurant currently has an active
     * flash discount. Idempotent — no dish id needed.
     */
    public function clearDiscount(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        Dish::where('restaurant_id', $staff->restaurant_id)
            ->whereNotNull('discount_percent')
            ->update([
                'discount_percent' => null,
                'discount_ends_at' => null,
                'discount_portions_total' => null,
                'discount_portions_remaining' => null,
            ]);

        return response()->json(['message' => 'Chegirma bekor qilindi.']);
    }
}
