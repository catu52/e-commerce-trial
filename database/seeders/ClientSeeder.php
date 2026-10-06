<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'first_name' => 'Alice',
                'last_name' => 'Buyer',
                'email' => 'alice@example.com',
                'phone' => '+1555010001',
                'password' => Hash::make('password'),
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Shopper',
                'email' => 'bob@example.com',
                'phone' => '+1555010002',
                'password' => Hash::make('password'),
            ],
            [
                'first_name' => 'Charlie',
                'last_name' => 'Customer',
                'email' => 'charlie@example.com',
                'phone' => '+1555010003',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($clients as $client) {
            DB::table('clients')->updateOrInsert(
                ['email' => $client['email']],
                array_merge($client, [
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
