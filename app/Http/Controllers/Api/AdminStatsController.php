<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    /**
     * Today's order stats for the admin's own restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff');

        $todaysOrders = Order::query()
            ->where('restaurant_id', $staff->restaurant_id)
            ->whereDate('created_at', today());

        $totalOrders = (clone $todaysOrders)->where('status', '!=', Order::STATUS_CANCELLED)->count();
        $completedOrders = (clone $todaysOrders)->where('status', Order::STATUS_PAID)->count();
        $totalRevenue = (clone $todaysOrders)->where('status', Order::STATUS_PAID)->sum('total_price');

        return response()->json([
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'total_revenue' => (float) $totalRevenue,
        ]);
    }
}
