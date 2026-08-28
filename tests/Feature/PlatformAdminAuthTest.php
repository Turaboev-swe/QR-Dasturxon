<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard isolation for the Filament /admin panel — see PlatformAdmin,
 * config/auth.php ('platform_admin' guard/provider), and
 * AdminPanelProvider::authGuard(). A separate, unrelated auth mechanism
 * from customer (telegram.auth/TelegramUser) and staff (staff.auth/Staff).
 */
class PlatformAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_login_page(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_a_platform_admin_can_reach_the_dashboard(): void
    {
        $admin = PlatformAdmin::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($admin, 'platform_admin')->get('/admin');

        $response->assertStatus(200);
    }

    public function test_being_logged_in_on_the_unrelated_web_guard_does_not_grant_admin_panel_access(): void
    {
        // The panel checks the `platform_admin` guard specifically
        // (AdminPanelProvider::authGuard()) — a session on the stock `web`
        // guard (unused elsewhere in this app; customers/staff never touch
        // any Laravel guard at all, see CLAUDE.md) must not leak access.
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/admin');

        $response->assertRedirect('/admin/login');
    }
}
