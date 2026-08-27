<?php

namespace Tests\Feature;

use Tests\TestCase;

class QrBridgeTest extends TestCase
{
    public function test_it_redirects_a_valid_start_param_to_the_telegram_mini_app_link(): void
    {
        config([
            'services.telegram.bot_username' => 'qr_dasturxon_bot',
            'services.telegram.miniapp_short_name' => 'qrmenu',
        ]);

        $response = $this->get('/t/r1_t5');

        $response->assertRedirect('https://t.me/qr_dasturxon_bot/qrmenu?startapp=r1_t5');
    }

    public function test_it_404s_on_a_malformed_start_param(): void
    {
        $response = $this->get('/t/table_5');

        $response->assertStatus(404);
    }

    public function test_it_forwards_the_start_param_without_resolving_it(): void
    {
        // No restaurant/table with id 999 exists — the bridge must not
        // care, since the real check happens later via TableResolver
        // inside the Mini App itself, not here.
        $response = $this->get('/t/r999_t1');

        $response->assertRedirect('https://t.me/qr_dasturxon_bot/qrmenu?startapp=r999_t1');
    }
}
