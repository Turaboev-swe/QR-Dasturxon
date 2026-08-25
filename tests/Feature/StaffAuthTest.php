<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Staff;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    use RefreshDatabase;

    private function initDataHeaderFor(int $telegramId): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Staff', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    public function test_a_registered_staff_telegram_id_resolves_via_me(): void
    {
        $restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        Staff::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Cashier',
            'role' => Staff::ROLE_CASHIER,
            'telegram_id' => 555000111,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/staff/me', $this->initDataHeaderFor(555000111));

        $response->assertStatus(200)->assertJson([
            'staff' => ['name' => 'Cashier', 'role' => 'cashier', 'restaurant_id' => $restaurant->id],
        ]);
    }

    public function test_an_unregistered_telegram_id_is_rejected(): void
    {
        $response = $this->getJson('/api/staff/me', $this->initDataHeaderFor(999999999));

        $response->assertStatus(403);
    }

    public function test_an_inactive_staff_member_is_rejected(): void
    {
        $restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        Staff::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Former Cashier',
            'role' => Staff::ROLE_CASHIER,
            'telegram_id' => 555000222,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/staff/me', $this->initDataHeaderFor(555000222));

        $response->assertStatus(403);
    }
}
