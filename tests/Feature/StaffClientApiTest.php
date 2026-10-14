<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffClientApiTest extends TestCase
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

    private function client(string $email = 'a@example.com'): Client
    {
        return Client::create(['first_name' => 'Ana', 'last_name' => 'Lopez', 'email' => $email, 'password' => 'password']);
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/staff/clients')->assertUnauthorized();
    }

    public function test_list_search_and_show(): void
    {
        $this->as('support@system.local');
        $a = $this->client('ana@example.com');
        $this->client('bob@example.com');

        $this->getJson('/api/v1/staff/clients?search=ana@')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'ana@example.com');
        $this->getJson("/api/v1/staff/clients/{$a->id}")->assertOk()->assertJsonPath('data.ordersCount', 0)->assertJsonMissingPath('data.password');
    }

    public function test_create_and_update(): void
    {
        $this->as('support@system.local');

        $id = $this->postJson('/api/v1/staff/clients', ['first_name' => 'Zed', 'last_name' => 'Q', 'email' => 'zed@example.com', 'password' => 'secret123', 'email_verified' => true])
            ->assertCreated()->assertJsonPath('data.email', 'zed@example.com')->json('data.id');
        $this->assertNotNull(Client::find($id)->email_verified_at);

        $this->postJson('/api/v1/staff/clients', ['first_name' => 'Z', 'last_name' => 'Q', 'email' => 'zed@example.com', 'password' => 'secret123'])->assertUnprocessable();
        $this->putJson("/api/v1/staff/clients/{$id}", ['phone' => '123', 'password' => 'x12345678'])->assertUnprocessable();
        $this->putJson("/api/v1/staff/clients/{$id}", ['phone' => '123', 'email' => 'zed@example.com'])->assertOk()->assertJsonPath('data.phone', '123');
    }

    public function test_password_and_sessions(): void
    {
        $this->as('support@system.local');
        $c = $this->client();
        $c->createToken('t');

        $this->postJson("/api/v1/staff/clients/{$c->id}/revoke-sessions")->assertOk()->assertJsonPath('revoked', 1);
        $c->createToken('t');
        $this->postJson("/api/v1/staff/clients/{$c->id}/password", ['password' => 'short'])->assertUnprocessable();
        $this->postJson("/api/v1/staff/clients/{$c->id}/password", ['password' => 'newpassword1'])->assertOk();
        $this->assertSame(0, $c->tokens()->count());
        $this->assertTrue(\Hash::check('newpassword1', $c->fresh()->password));
    }

    public function test_delete_only_without_orders(): void
    {
        $this->as('admin@system.local');
        $free = $this->client('free@example.com');
        $this->deleteJson("/api/v1/staff/clients/{$free->id}")->assertOk();
        $this->assertNull(Client::find($free->id));
    }

    public function test_delete_blocked_with_orders_and_orders_listing(): void
    {
        $this->as('admin@system.local');
        $c = $this->client();
        $pid = Product::create(['sku' => 'S1', 'name' => 'P', 'slug' => 'p', 'base_price' => 10, 'stock_quantity' => 1])->id;
        $order = (new Order)->forceFill(['product_id' => $pid, 'quantity' => 1, 'client_id' => $c->id, 'order_number' => 'KR-1', 'status' => 'pending', 'email' => $c->email, 'subtotal' => 1, 'delivery_cost' => 0, 'discount' => 0, 'total_amount' => 1, 'currency' => 'EUR', 'delivery_method' => 'standard']);
        $order->save();

        $this->deleteJson("/api/v1/staff/clients/{$c->id}")->assertUnprocessable();
        $this->getJson("/api/v1/staff/clients/{$c->id}/orders")->assertOk()->assertJsonPath('data.0.orderNumber', 'KR-1');
        $this->getJson('/api/v1/staff/clients?has_orders=1')->assertJsonCount(1, 'data');
    }

    public function test_manager_can_view_but_not_manage(): void
    {
        $this->as('manager@system.local');
        $c = $this->client();

        $this->getJson('/api/v1/staff/clients')->assertOk();
        $this->postJson("/api/v1/staff/clients/{$c->id}/revoke-sessions")->assertForbidden();
        $this->deleteJson("/api/v1/staff/clients/{$c->id}")->assertForbidden();
        $this->postJson('/api/v1/staff/clients', [])->assertForbidden();
    }
}
