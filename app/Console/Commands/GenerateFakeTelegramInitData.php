<?php

namespace App\Console\Commands;

use App\Services\TelegramAuth;
use Illuminate\Console\Command;

class GenerateFakeTelegramInitData extends Command
{
    protected $signature = 'telegram:fake-init-data
        {--telegram-id=111222333 : Fake Telegram user id}
        {--first-name=Test : Fake Telegram first name}
        {--username=test_user : Fake Telegram username}
        {--language-code=uz : Fake Telegram client language}
        {--start-param=r1_t1 : start_param in the form r<restaurant_id>_t<table_code>}
        {--url=http://127.0.0.1:8000/api/session : Endpoint to build the curl example for}';

    protected $description = 'Generate a validly-signed fake Telegram initData string (dev/testing only) and a ready-to-run curl example';

    public function handle(TelegramAuth $telegramAuth): int
    {
        $userJson = json_encode([
            'id' => (int) $this->option('telegram-id'),
            'first_name' => $this->option('first-name'),
            'username' => $this->option('username'),
            'language_code' => $this->option('language-code'),
        ]);

        $initData = $telegramAuth->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA'.bin2hex(random_bytes(8)),
            'start_param' => $this->option('start-param'),
            'user' => $userJson,
        ]);

        $this->line($initData);
        $this->newLine();
        $this->line(sprintf(
            "curl -s -X POST '%s' \\\n  -H 'X-Telegram-Init-Data: %s'",
            $this->option('url'),
            $initData,
        ));

        return self::SUCCESS;
    }
}
