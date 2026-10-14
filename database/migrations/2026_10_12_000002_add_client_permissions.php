<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{display: string, description: string, roles: list<string>}> */
    private const PERMISSIONS = [
        'clients.view' => ['display' => 'View Customers', 'description' => 'View storefront customer accounts and their orders', 'roles' => ['admin', 'manager', 'support']],
        'clients.manage' => ['display' => 'Manage Customers', 'description' => 'Edit customer details, reset passwords, revoke sessions and delete customers without orders', 'roles' => ['admin', 'support']],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $meta) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['display_name' => $meta['display'], 'description' => $meta['description'], 'created_at' => now(), 'updated_at' => now()]
            );

            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            foreach (DB::table('roles')->whereIn('name', $meta['roles'])->pluck('id') as $roleId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};
