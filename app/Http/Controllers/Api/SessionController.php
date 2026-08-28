<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidQrSessionException;
use App\Http\Controllers\Concerns\ResolvesLocale;
use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use App\Services\DailyStatsService;
use App\Services\TableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    use ResolvesLocale;

    public function __construct(
        private readonly TableResolver $tableResolver,
        private readonly DailyStatsService $dailyStats,
    ) {}

    /**
     * Resolve the restaurant/table encoded in the QR-signed start_param.
     * The QR code itself is table-specific (one code per table), so simply
     * possessing a valid, signed start_param is sufficient proof of context
     * — no additional device geolocation check is required.
     */
    public function resolve(Request $request): JsonResponse
    {
        $initData = $request->attributes->get('telegramInitData', []);

        try {
            ['restaurant' => $restaurant, 'table' => $table] = $this->tableResolver->resolve(
                $initData['start_param'] ?? null,
            );
        } catch (InvalidQrSessionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        /** @var TelegramUser|null $telegramUser */
        $telegramUser = $request->attributes->get('telegramUser');
        $locale = $this->resolveLocale($request, $telegramUser);

        if ($telegramUser) {
            $this->dailyStats->recordVisit($restaurant, $telegramUser);
        }

        return response()->json([
            'language' => $locale,
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->translate('name_translations', $locale),
            ],
            'table' => [
                'id' => $table->id,
                'code' => $table->code,
                'name' => $table->name,
            ],
        ]);
    }
}
