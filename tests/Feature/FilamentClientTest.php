<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Models\Client;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function staff(string $name): User
    {
        return User::where('email', "{$name}@system.local")->firstOrFail();
    }

    private function client(string $email = 'jane@example.com'): Client
    {
        return Client::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => $email, 'password' => 'secret123']);
    }

    private function orderFor(Client $client): Order
    {
        $order = new Order;
        $order->forceFill(['client_id' => $client->id, 'product_id' => Product::create(['sku' => 'S'.$client->id, 'name' => 'P', 'slug' => 'p'.$client->id, 'base_price' => 10, 'stock_quantity' => 1])->id, 'quantity' => 1, 'status' => 'completed', 'total_amount' => 10])->save();

        return $order;
    }

    public function test_admin_and_support_and_manager_can_view_list(): void
    {
        foreach (['admin', 'manager', 'support'] as $role) {
            $this->actingAs($this->staff($role));
            $this->assertTrue(ClientResource::canViewAny(), $role);
        }
    }

    public function test_manager_can_view_but_not_manage(): void
    {
        $client = $this->client();
        $this->actingAs($this->staff('manager'));

        $this->assertFalse(ClientResource::canEdit($client));
        $this->assertFalse(ClientResource::canDelete($client));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::create(['name' => 'N', 'email' => 'n@x.test', 'password' => 'password1']);
        $user->permissions()->attach(Permission::where('name', 'cms.access')->first());

        $this->actingAs($user)->get('/admin/clients')->assertForbidden();
    }

    public function test_list_page_renders_and_lists_clients(): void
    {
        $client = $this->client();
        $this->actingAs($this->staff('support'))->get('/admin/clients')->assertOk();

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$client])
            ->searchTable('jane@')
            ->assertCanSeeTableRecords([$client])
            ->searchTable('nobody')
            ->assertCanNotSeeTableRecords([$client]);
    }

    public function test_support_edits_a_client(): void
    {
        $client = $this->client();
        $this->actingAs($this->staff('support'));

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['first_name' => 'Janet', 'phone' => '+34123'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Janet', $client->fresh()->first_name);
    }

    public function test_email_must_stay_unique(): void
    {
        $this->client('taken@example.com');
        $client = $this->client();
        $this->actingAs($this->staff('admin'));

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['email' => 'taken@example.com'])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_set_password_revokes_sessions(): void
    {
        $client = $this->client();
        $client->createToken('buyer_auth_token');
        $this->actingAs($this->staff('support'));

        Livewire::test(ListClients::class)
            ->callTableAction('resetPassword', $client, ['password' => 'brand-new-pass'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $client->fresh()->password));
        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_revoke_sessions_action(): void
    {
        $client = $this->client();
        $client->createToken('a');
        $client->createToken('b');
        $this->actingAs($this->staff('admin'));

        Livewire::test(ListClients::class)->callTableAction('revokeSessions', $client);

        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_manager_cannot_run_account_actions(): void
    {
        $client = $this->client();
        $client->createToken('a');
        $this->actingAs($this->staff('manager'));

        Livewire::test(ListClients::class)
            ->assertTableActionHidden('resetPassword', $client)
            ->assertTableActionHidden('revokeSessions', $client);

        $this->assertSame(1, $client->tokens()->count());
    }

    public function test_client_without_orders_can_be_deleted_but_not_with_orders(): void
    {
        $plain = $this->client('plain@example.com');
        $buyer = $this->client('buyer@example.com');
        $this->orderFor($buyer);
        $this->actingAs($this->staff('admin'));

        $this->assertTrue(ClientResource::canDelete($plain));
        $this->assertFalse(ClientResource::canDelete($buyer));

        Livewire::test(ListClients::class)->callTableAction('delete', $plain);

        $this->assertDatabaseMissing('clients', ['id' => $plain->id]);
        $this->assertDatabaseHas('clients', ['id' => $buyer->id]);
        $this->assertSame(1, Order::where('client_id', $buyer->id)->count());
    }
}
