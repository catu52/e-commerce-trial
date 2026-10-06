<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roleMap = DB::table('roles')->pluck('id', 'name');

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@system.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Campaign Manager',
                'email' => 'manager@system.local',
                'password' => Hash::make('password'),
                'role' => 'manager',
            ],
            [
                'name' => 'Support Agent',
                'email' => 'support@system.local',
                'password' => Hash::make('password'),
                'role' => 'support',
            ],
        ];

        foreach ($users as $userData) {
            $roleName = $userData['role'];
            unset($userData['role']);

            $userId = DB::table('users')->insertGetId(array_merge($userData, [
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            // Attach Role
            if (isset($roleMap[$roleName])) {
                DB::table('role_user')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleMap[$roleName],
                ]);
            }
        }
    }
}
