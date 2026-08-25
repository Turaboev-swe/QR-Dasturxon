<?php

namespace App\Services;

use App\Exceptions\InvalidTelegramInitDataException;

class TelegramAuth
{
    /**
     * Maximum age (in seconds) an initData payload is considered fresh.
     */
    private const MAX_AUTH_AGE_SECONDS = 86400;

    public function __construct(
        private readonly string $botToken,
    ) {}

    /**
     * Verify a Telegram Mini App `initData` string and return its decoded fields.
     *
     * @return array{
     *     user: array|null,
     *     start_param: string|null,
     *     auth_date: int|null,
     *     query_id: string|null,
     * }
     *
     * @throws InvalidTelegramInitDataException
     */
    public function validate(string $initData): array
    {
        if ($initData === '' || $this->botToken === '') {
            throw new InvalidTelegramInitDataException('Telegram initData yoki bot token bo\'sh.');
        }

        parse_str($initData, $data);

        $hash = $data['hash'] ?? null;

        if (! is_string($hash) || $hash === '') {
            throw new InvalidTelegramInitDataException('Telegram initData hash mavjud emas.');
        }

        unset($data['hash']);
        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            throw new InvalidTelegramInitDataException('Telegram initData imzosi noto\'g\'ri.');
        }

        $authDate = isset($data['auth_date']) ? (int) $data['auth_date'] : null;

        if ($authDate === null || (time() - $authDate) > self::MAX_AUTH_AGE_SECONDS) {
            throw new InvalidTelegramInitDataException('Telegram initData muddati o\'tgan.');
        }

        $user = null;

        if (isset($data['user'])) {
            $decoded = json_decode((string) $data['user'], true);
            $user = is_array($decoded) ? $decoded : null;
        }

        return [
            'user' => $user,
            'start_param' => $data['start_param'] ?? null,
            'auth_date' => $authDate,
            'query_id' => $data['query_id'] ?? null,
        ];
    }

    /**
     * Sign a set of initData fields exactly as Telegram would, appending the
     * resulting `hash`. Intended for local/dev tooling (e.g. generating a
     * fake initData string to test against); never used on the request path.
     */
    public function sign(array $fields): string
    {
        ksort($fields);

        $dataCheckString = collect($fields)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return collect($fields)
            ->map(fn ($value, $key) => $key.'='.rawurlencode((string) $value))
            ->implode('&');
    }
}
