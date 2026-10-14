<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            ClientSeeder::class,
            CategorySeeder::class,
            ProductAndFlashSaleSeeder::class,
            ProductSaleSeeder::class,
            OrderSeeder::class,
            ContentSeeder::class,
            ArticleSeeder::class,
        ]);
    }
}
