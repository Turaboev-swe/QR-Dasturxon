<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function __construct(
        private readonly string $botToken,
    ) {}

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): array
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<string, mixed>
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): array
    {
        return $this->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params): array
    {
        // Telegram's Bot API expects `reply_markup` as a JSON-encoded string
        // when the request body isn't raw JSON.
        if (isset($params['reply_markup'])) {
            $params['reply_markup'] = json_encode($params['reply_markup']);
        }

        $response = Http::asForm()
            ->post("https://api.telegram.org/bot{$this->botToken}/{$method}", array_filter(
                $params,
                fn ($value) => $value !== null,
            ));

        $result = $response->json() ?? [];

        // Http::post() only throws on network-level failures, never on a
        // well-formed-but-unsuccessful Telegram response (`ok: false`, e.g.
        // "chat not found" because the bot was never added to that group)
        // — without this, such failures were silently swallowed by every
        // caller's try/catch, with nothing in the logs to explain why.
        if (($result['ok'] ?? null) !== true) {
            Log::warning('telegram.api_call_failed', [
                'method' => $method,
                'chat_id' => $params['chat_id'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'description' => $result['description'] ?? 'no response body',
            ]);
        }

        return $result;
    }
}
