<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define Standard Permissions
        $permissions = [
            // Flash Sale Management
            ['name' => 'flash-sales.view', 'display_name' => 'View Flash Sales', 'description' => 'View active and scheduled flash sales'],
            ['name' => 'flash-sales.create', 'display_name' => 'Create Flash Sales', 'description' => 'Create new flash sale events'],
            ['name' => 'flash-sales.update', 'display_name' => 'Update Flash Sales', 'description' => 'Edit flash sale details and stock allocations'],
            ['name' => 'flash-sales.delete', 'display_name' => 'Delete Flash Sales', 'description' => 'Cancel or remove flash sale events'],

            // Product Catalog
            ['name' => 'products.manage', 'display_name' => 'Manage Products', 'description' => 'Create, edit, and update base product catalog'],

            // Order & Customer Service Operations
            ['name' => 'orders.view', 'display_name' => 'View Orders', 'description' => 'View client order details and status'],
            ['name' => 'orders.refund', 'display_name' => 'Refund Orders', 'description' => 'Process refunds and cancel placed orders'],
            ['name' => 'reservations.inspect', 'display_name' => 'Inspect Reservations', 'description' => 'Monitor real-time Redis/SQL stock holds'],

            // System Administration
            ['name' => 'users.manage', 'display_name' => 'Manage Staff Users', 'description' => 'Manage CMS users, roles, and permissions'],
            ['name' => 'system.metrics', 'display_name' => 'View System Metrics', 'description' => 'Access system performance and Queue telemetry'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                array_merge($permission, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 2. Define Roles
        $roles = [
            ['name' => 'admin', 'display_name' => 'System Administrator', 'description' => 'Full administrative access across all modules'],
            ['name' => 'manager', 'display_name' => 'Campaign Manager', 'description' => 'Manages products, flash sales, and campaign parameters'],
            ['name' => 'support', 'display_name' => 'Customer Support', 'description' => 'Handles order lookups, refunds, and reservation queries'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                array_merge($role, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Fetch ID maps
        $permissionMap = DB::table('permissions')->pluck('id', 'name');
        $roleMap = DB::table('roles')->pluck('id', 'name');

        // 3. Attach Permissions to Roles

        // Admin gets ALL permissions
        foreach ($permissionMap as $permId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleMap['admin'],
                'permission_id' => $permId,
            ]);
        }

        // Manager permissions
        $managerPermissions = [
            'flash-sales.view',
            'flash-sales.create',
            'flash-sales.update',
            'flash-sales.delete',
            'products.manage',
            'orders.view',
            'reservations.inspect',
            'system.metrics'
        ];
        foreach ($managerPermissions as $permName) {
            if (isset($permissionMap[$permName])) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleMap['manager'],
                    'permission_id' => $permissionMap[$permName],
                ]);
            }
        }

        // Support permissions
        $supportPermissions = ['flash-sales.view', 'orders.view', 'orders.refund', 'reservations.inspect'];
        foreach ($supportPermissions as $permName) {
            if (isset($permissionMap[$permName])) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleMap['support'],
                    'permission_id' => $permissionMap[$permName],
                ]);
            }
        }
    }
}
