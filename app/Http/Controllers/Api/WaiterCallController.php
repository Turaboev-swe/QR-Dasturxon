<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidQrSessionException;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\TelegramUser;
use App\Models\WaiterCall;
use App\Services\TableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaiterCallController extends Controller
{
    public function __construct(
        private readonly TableResolver $tableResolver,
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

        $call = WaiterCall::create([
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $table->id,
            'telegram_user_id' => $telegramUser->id,
            'status' => WaiterCall::STATUS_PENDING,
            'type' => $type,
        ]);

        return response()->json(['waiter_call' => $call], 201);
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
