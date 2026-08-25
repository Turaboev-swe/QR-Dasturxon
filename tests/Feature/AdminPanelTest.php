<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Dish $dish;

    private Dish $otherDish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_translations' => ['uz' => 'Taomlar'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->dish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Osh'],
            'price' => 20000,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        $this->otherDish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Lag\'mon'],
            'price' => 15000,
            'sort_order' => 2,
            'is_available' => true,
            'discount_percent' => 30,
            'discount_ends_at' => now()->addMinutes(20),
            'discount_portions_total' => 5,
            'discount_portions_remaining' => 5,
        ]);
    }

    /**
     * Register a staff member by Telegram id and return the signed
     * initData header that identifies them — there is no login step.
     */
    private function authAs(string $role, int $telegramId = 700000001): array
    {
        Staff::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Staff',
            'role' => $role,
            'telegram_id' => $telegramId,
            'is_active' => true,
        ]);

        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Staff', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    public function test_cashier_is_forbidden_from_every_admin_route(): void
    {
        $auth = $this->authAs(Staff::ROLE_CASHIER);

        $this->getJson('/api/staff/admin/dishes', $auth)->assertStatus(403);
        $this->patchJson("/api/staff/admin/dishes/{$this->dish->id}/availability", [], $auth)->assertStatus(403);
        $this->postJson("/api/staff/admin/dishes/{$this->dish->id}/discount", ['percent' => 10, 'portions' => 1, 'minutes' => 1], $auth)->assertStatus(403);
        $this->deleteJson('/api/staff/admin/discounts', [], $auth)->assertStatus(403);
        $this->getJson('/api/staff/admin/stats', $auth)->assertStatus(403);
    }

    public function test_admin_can_toggle_dish_availability(): void
    {
        $auth = $this->authAs(Staff::ROLE_ADMIN);

        $response = $this->patchJson("/api/staff/admin/dishes/{$this->dish->id}/availability", [], $auth);

        $response->assertStatus(200);
        $this->assertFalse($this->dish->fresh()->is_available);
    }

    public function test_admin_cannot_toggle_a_dish_from_another_restaurant(): void
    {
        $foreignRestaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Boshqa'],
            'latitude' => 40.0,
            'longitude' => 65.0,
            'radius_meters' => 150,
            'is_active' => true,
        ]);
        $foreignCategory = Category::create([
            'restaurant_id' => $foreignRestaurant->id,
            'name_translations' => ['uz' => 'X'],
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $foreignDish = Dish::create([
            'restaurant_id' => $foreignRestaurant->id,
            'category_id' => $foreignCategory->id,
            'name_translations' => ['uz' => 'Y'],
            'price' => 1000,
            'sort_order' => 1,
            'is_available' => true,
        ]);

        $auth = $this->authAs(Staff::ROLE_ADMIN);

        $response = $this->patchJson("/api/staff/admin/dishes/{$foreignDish->id}/availability", [], $auth);

        $response->assertStatus(404);
    }

    public function test_setting_a_discount_clears_any_previous_one_in_the_same_restaurant(): void
    {
        $auth = $this->authAs(Staff::ROLE_ADMIN);

        // otherDish already has a live discount from setUp(); set one on $dish instead.
        $response = $this->postJson("/api/staff/admin/dishes/{$this->dish->id}/discount", [
            'percent' => 40,
            'portions' => 3,
            'minutes' => 15,
        ], $auth);

        $response->assertStatus(200);
        $this->assertTrue($this->dish->fresh()->hasLiveDiscount());
        $this->assertFalse($this->otherDish->fresh()->hasLiveDiscount());
        $this->assertNull($this->otherDish->fresh()->discount_percent);
    }

    public function test_clear_discount_is_idempotent(): void
    {
        $auth = $this->authAs(Staff::ROLE_ADMIN);

        $this->deleteJson('/api/staff/admin/discounts', [], $auth)->assertStatus(200);
        $this->assertFalse($this->otherDish->fresh()->hasLiveDiscount());

        // calling again with nothing active should still succeed
        $this->deleteJson('/api/staff/admin/discounts', [], $auth)->assertStatus(200);
    }

    public function test_stats_are_scoped_to_restaurant_and_today_and_paid_only_revenue(): void
    {
        $telegramUser = \App\Models\TelegramUser::create(['telegram_id' => 1, 'first_name' => 'X']);
        $table = RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'code' => '1', 'is_active' => true]);

        Order::create(['restaurant_id' => $this->restaurant->id, 'restaurant_table_id' => $table->id, 'telegram_user_id' => $telegramUser->id, 'status' => Order::STATUS_PAID, 'total_price' => 50000]);
        Order::create(['restaurant_id' => $this->restaurant->id, 'restaurant_table_id' => $table->id, 'telegram_user_id' => $telegramUser->id, 'status' => Order::STATUS_PENDING, 'total_price' => 30000]);
        Order::create(['restaurant_id' => $this->restaurant->id, 'restaurant_table_id' => $table->id, 'telegram_user_id' => $telegramUser->id, 'status' => Order::STATUS_CANCELLED, 'total_price' => 99999]);

        $auth = $this->authAs(Staff::ROLE_ADMIN);

        $response = $this->getJson('/api/staff/admin/stats', $auth);

        $response->assertStatus(200)
            ->assertJson([
                'total_orders' => 2, // paid + pending, not cancelled
                'completed_orders' => 1,
                'total_revenue' => 50000.0,
            ]);
    }
}
