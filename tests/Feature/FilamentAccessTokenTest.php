<?php

namespace Tests\Feature;

use App\Filament\Resources\AccessTokenResource;
use App\Filament\Resources\AccessTokenResource\Pages\ListAccessTokens;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentAccessTokenTest extends TestCase
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

    private function clientToken(): PersonalAccessToken
    {
        $client = Client::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'c@example.com', 'password' => 'secret123']);

        return $client->createToken('buyer_auth_token')->accessToken;
    }

    public function test_admin_can_open_the_tokens_section(): void
    {
        $this->actingAs($this->staff('admin'))->get('/admin/access-tokens')->assertOk();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/access-tokens')->assertRedirect('/admin/login');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_staff_cannot_open_the_tokens_section(string $role): void
    {
        $this->actingAs($this->staff($role))->get('/admin/access-tokens')->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return ['manager' => ['manager'], 'support' => ['support']];
    }

    private function navigationLabels(): Collection
    {
        return collect(Filament::getPanel('admin')->getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->map->getLabel();
    }

    public function test_navigation_entry_is_hidden_from_non_admins(): void
    {
        $this->actingAs($this->staff('manager'));

        $this->assertFalse($this->navigationLabels()->contains('Access tokens'));
    }

    public function test_navigation_entry_is_visible_to_admins(): void
    {
        $this->actingAs($this->staff('admin'));

        $this->assertTrue($this->navigationLabels()->contains('Access tokens'));
    }

    public function test_lists_staff_and_client_tokens_without_exposing_hashes(): void
    {
        $this->clientToken();
        $this->staff('manager')->createToken('staff_auth_token', ['staff']);
        $this->actingAs($this->staff('admin'));

        $tokens = PersonalAccessToken::all();

        Livewire::test(ListAccessTokens::class)
            ->assertCanSeeTableRecords($tokens)
            ->assertSee('c@example.com')
            ->assertDontSee($tokens->first()->token)
            ->filterTable('tokenable_type', Client::class)
            ->assertCountTableRecords(1);
    }

    public function test_admin_revokes_a_token(): void
    {
        $token = $this->clientToken();
        $this->actingAs($this->staff('admin'));

        Livewire::test(ListAccessTokens::class)->callTableAction('delete', $token);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_admin_bulk_revokes_tokens(): void
    {
        $a = $this->clientToken();
        $b = $this->staff('support')->createToken('x', ['staff'])->accessToken;
        $this->actingAs($this->staff('admin'));

        Livewire::test(ListAccessTokens::class)->callTableBulkAction('delete', [$a, $b]);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_admin_creates_a_staff_token(): void
    {
        $manager = $this->staff('manager');
        $this->actingAs($this->staff('admin'));

        Livewire::test(ListAccessTokens::class)
            ->callAction('createToken', ['user_id' => $manager->id, 'name' => 'ci-token', 'abilities' => ['staff']])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $stored = PersonalAccessToken::where('name', 'ci-token')->firstOrFail();
        $this->assertSame(User::class, $stored->tokenable_type);
        $this->assertSame(['staff'], $stored->abilities);
        $this->assertNull($stored->expires_at);
    }

    public function test_create_requires_name_and_staff_user(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(ListAccessTokens::class)
            ->callAction('createToken', ['name' => ''])
            ->assertHasActionErrors(['user_id' => 'required', 'name' => 'required']);
    }

    public function test_non_admin_cannot_manage_tokens_even_with_cms_access(): void
    {
        $token = $this->clientToken();
        $this->actingAs($this->staff('manager'));

        $this->assertFalse(AccessTokenResource::canViewAny());
        $this->assertFalse(AccessTokenResource::canDeleteAny());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }
}
