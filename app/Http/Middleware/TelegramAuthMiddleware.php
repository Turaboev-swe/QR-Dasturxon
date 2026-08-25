<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidTelegramInitDataException;
use App\Models\TelegramUser;
use App\Services\TelegramAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramAuthMiddleware
{
    public function __construct(
        private readonly TelegramAuth $telegramAuth,
    ) {}

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

        $telegramUser = TelegramUser::updateOrCreate(
            ['telegram_id' => $telegramUserData['id']],
            [
                'first_name' => $telegramUserData['first_name'] ?? '',
                'last_name' => $telegramUserData['last_name'] ?? null,
                'username' => $telegramUserData['username'] ?? null,
                'language_code' => $telegramUserData['language_code'] ?? null,
                'photo_url' => $telegramUserData['photo_url'] ?? null,
            ],
        );

        $request->attributes->set('telegramInitData', $parsed);
        $request->attributes->set('telegramUser', $telegramUser);

        return $next($request);
    }
}
