<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionInitTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani', 'en' => 'Sample Restaurant'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => '1',
            'name' => 'Stol 1',
            'is_active' => true,
        ]);
    }

    private function signInitData(array $fields): string
    {
        return app(TelegramAuth::class)->sign($fields);
    }

    private function initDataFields(string $startParam): array
    {
        return [
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'start_param' => $startParam,
            'user' => json_encode(['id' => 111222333, 'first_name' => 'Test', 'language_code' => 'uz']),
        ];
    }

    public function test_invalid_signature_returns_401(): void
    {
        $initData = $this->signInitData($this->initDataFields('r'.$this->restaurant->id.'_t1'));
        $tampered = $initData.'tampered';

        $response = $this->postJson('/api/session', [], ['X-Telegram-Init-Data' => $tampered]);

        $response->assertStatus(401);
    }

    public function test_invalid_start_param_returns_422(): void
    {
        $initData = $this->signInitData($this->initDataFields('r999999_t1'));

        $response = $this->postJson('/api/session', [], ['X-Telegram-Init-Data' => $initData]);

        $response->assertStatus(422);
    }

    public function test_valid_request_returns_200_with_expected_fields(): void
    {
        $initData = $this->signInitData($this->initDataFields('r'.$this->restaurant->id.'_t1'));

        $response = $this->postJson('/api/session', [], ['X-Telegram-Init-Data' => $initData]);

        $response->assertStatus(200)
            ->assertJson([
                'language' => 'uz',
                'restaurant' => [
                    'id' => $this->restaurant->id,
                    'name' => 'Namuna Restorani',
                ],
                'table' => [
                    'id' => $this->table->id,
                    'code' => '1',
                    'name' => 'Stol 1',
                ],
            ]);
    }
}
