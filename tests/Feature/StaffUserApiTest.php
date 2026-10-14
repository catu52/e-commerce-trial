<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffUserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
    }

    private function as(string $email): void
    {
        Sanctum::actingAs(User::where('email', $email)->firstOrFail(), [], 'sanctum_users');
    }

    public function test_admin_can_list_and_show_users(): void
    {
        $this->as('admin@system.local');

        $this->getJson('/api/v1/staff/users')->assertOk();
        $id = User::where('email', 'manager@system.local')->value('id');
        $this->getJson("/api/v1/staff/users/{$id}")->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->as('support@system.local');

        $this->getJson('/api/v1/staff/users')->assertForbidden();
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/staff/users')->assertUnauthorized();
    }
}
