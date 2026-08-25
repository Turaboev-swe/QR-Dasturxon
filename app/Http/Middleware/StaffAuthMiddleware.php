<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidTelegramInitDataException;
use App\Models\Staff;
use App\Services\TelegramAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffAuthMiddleware
{
    public function __construct(
        private readonly TelegramAuth $telegramAuth,
    ) {}

    /**
     * Staff are identified purely by their Telegram id, pre-registered in
     * the `staff` table by the restaurant — same "no login screen" model
     * as customers, just checked against a different table.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Telegram-Init-Data', '');

        if (! is_string($initData) || $initData === '') {
            return response()->json(['message' => 'X-Telegram-Init-Data header talab qilinadi.'], 401);
        }

        try {
            $parsed = $this->telegramAuth->validate($initData);
        } catch (InvalidTelegramInitDataException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $telegramUserData = $parsed['user'];

        if (! is_array($telegramUserData) || ! isset($telegramUserData['id'])) {
            return response()->json(['message' => 'Telegram foydalanuvchi ma\'lumoti topilmadi.'], 401);
        }

        $staff = Staff::where('telegram_id', $telegramUserData['id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return response()->json(['message' => 'Siz xodim sifatida ro\'yxatdan o\'tmagansiz.'], 403);
        }

        $request->attributes->set('staff', $staff);

        return $next($request);
    }
}
