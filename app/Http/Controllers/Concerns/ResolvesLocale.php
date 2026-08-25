<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TelegramUser;
use Illuminate\Http\Request;

trait ResolvesLocale
{
    private const SUPPORTED_LOCALES = ['uz', 'en', 'ru', 'ko', 'fr', 'zh'];

    private const DEFAULT_LOCALE = 'uz';

    /**
     * Resolve the response language: explicit `?lang=` query param first,
     * then the Telegram user's client language, then the app default.
     */
    protected function resolveLocale(Request $request, ?TelegramUser $telegramUser): string
    {
        $requested = $request->query('lang');

        if (is_string($requested) && in_array($requested, self::SUPPORTED_LOCALES, true)) {
            return $requested;
        }

        $preferred = $telegramUser?->language_code;

        if (is_string($preferred) && in_array($preferred, self::SUPPORTED_LOCALES, true)) {
            return $preferred;
        }

        return self::DEFAULT_LOCALE;
    }
}
