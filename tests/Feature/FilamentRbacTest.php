<?php

namespace Tests\Feature;

use App\Filament\Resources\PermissionResource;
use App\Filament\Resources\PermissionResource\Pages\EditPermission;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentRbacTest extends TestCase
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

    private function role(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    public static function screens(): array
    {
        return [['users'], ['roles'], ['permissions']];
    }

    #[DataProvider('screens')]
    public function test_admin_reaches_the_screen(string $path): void
    {
        $this->actingAs($this->staff('admin'))->get("/admin/{$path}")->assertOk();
    }

    #[DataProvider('screens')]
    public function test_manager_is_forbidden(string $path): void
    {
        $this->actingAs($this->staff('manager'))->get("/admin/{$path}")->assertForbidden();
    }

    #[DataProvider('screens')]
    public function test_support_is_forbidden(string $path): void
    {
        $this->actingAs($this->staff('support'))->get("/admin/{$path}")->assertForbidden();
    }

    public function test_navigation_hidden_for_non_admins(): void
    {
        $this->actingAs($this->staff('manager'));
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(PermissionResource::canViewAny());
    }

    public function test_a_custom_role_with_users_manage_grants_access(): void
    {
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR']);
        $role->permissions()->attach(Permission::whereIn('name', ['users.manage', 'cms.access'])->pluck('id'));
        $user = User::create(['name' => 'H', 'email' => 'h@x.test', 'password' => 'password1']);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/admin/users')->assertOk();
    }

    public function test_admin_creates_a_user_with_role(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'New Staff', 'email' => 'new@system.local', 'password' => 'secret-pass', 'roles' => [$this->role('support')->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'new@system.local')->firstOrFail();
        $this->assertTrue($user->hasRole('support'));
        $this->assertNotSame('secret-pass', $user->password);
    }

    public function test_create_validates_password_email_and_uniqueness(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'admin@system.local', 'password' => 'short'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique', 'password' => 'min']);
    }

    public function test_edit_keeps_password_when_left_empty_and_updates_roles(): void
    {
        $this->actingAs($this->staff('admin'));
        $support = $this->staff('support');
        $hash = $support->password;

        Livewire::test(EditUser::class, ['record' => $support->getRouteKey()])
            ->fillForm(['name' => 'Renamed', 'password' => '', 'roles' => [$this->role('manager')->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $support->refresh();
        $this->assertSame('Renamed', $support->name);
        $this->assertSame($hash, $support->password);
        $this->assertTrue($support->hasRole('manager'));
        $this->assertFalse($support->hasRole('support'));
    }

    public function test_cannot_delete_self_or_last_admin_but_can_delete_others_and_their_tokens(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin);

        $this->assertTrue(UserResource::isProtected($admin));

        $support = $this->staff('support');
        $support->createToken('t', ['staff']);

        Livewire::test(ListUsers::class)->callTableAction('delete', $support);

        $this->assertDatabaseMissing('users', ['id' => $support->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_last_admin_cannot_lose_admin_role(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['roles' => [$this->role('support')->id]])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_role_can_be_removed_when_another_admin_exists(): void
    {
        $admin = $this->staff('admin');
        $second = $this->staff('manager');
        $second->roles()->attach($this->role('admin'));
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $second->getRouteKey()])
            ->fillForm(['roles' => [$this->role('manager')->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($second->fresh()->hasRole('admin'));
    }

    public function test_creating_a_role_with_permissions(): void
    {
        $this->actingAs($this->staff('admin'));
        $ids = Permission::whereIn('name', ['orders.view', 'cms.access'])->pluck('id')->all();

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'viewer', 'display_name' => 'Viewer', 'permissions' => $ids])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['orders.view', 'cms.access'], $this->role('viewer')->permissions->pluck('name')->all());
    }

    public function test_role_permissions_can_be_edited_and_take_effect(): void
    {
        $this->actingAs($this->staff('admin'));
        $support = $this->role('support');
        $ids = Permission::whereIn('name', ['cms.access', 'products.manage'])->pluck('id')->all();

        Livewire::test(EditRole::class, ['record' => $support->getRouteKey()])
            ->fillForm(['permissions' => $ids])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->staff('support')->hasPermission('products.manage'));
        $this->assertFalse($this->staff('support')->hasPermission('orders.refund'));
    }

    public function test_built_in_roles_cannot_be_renamed_or_deleted(): void
    {
        $this->actingAs($this->staff('admin'));
        $manager = $this->role('manager');

        Livewire::test(EditRole::class, ['record' => $manager->getRouteKey()])
            ->fillForm(['name' => 'hacked', 'display_name' => 'Boss'])
            ->call('save');

        $manager->refresh();
        $this->assertSame('manager', $manager->name);
        $this->assertSame('Boss', $manager->display_name);
        $this->assertFalse(RoleResource::canDelete($manager));
    }

    public function test_custom_role_deletable_only_when_unused(): void
    {
        $this->actingAs($this->staff('admin'));
        $role = Role::create(['name' => 'temp', 'display_name' => 'Temp']);

        $this->assertTrue(RoleResource::canDelete($role));

        $role->users()->attach($this->staff('support'));
        $this->assertFalse(RoleResource::canDelete($role));

        $role->users()->detach();
        Livewire::test(ListRoles::class)->callTableAction('delete', $role);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_permissions_are_edit_only_and_name_is_immutable(): void
    {
        $this->actingAs($this->staff('admin'));
        $permission = Permission::where('name', 'orders.view')->firstOrFail();

        $this->assertFalse(PermissionResource::canCreate());
        $this->assertFalse(PermissionResource::canDelete($permission));

        Livewire::test(EditPermission::class, ['record' => $permission->getRouteKey()])
            ->fillForm(['display_name' => 'Look at orders'])
            ->call('save')
            ->assertHasNoFormErrors();

        $permission->refresh();
        $this->assertSame('orders.view', $permission->name);
        $this->assertSame('Look at orders', $permission->display_name);
    }
}
