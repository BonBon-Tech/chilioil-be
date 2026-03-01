<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only creates roles and features/plan-features.
     * Real production data (stores, products, users, etc.) is imported via:
     *   php artisan import:production-data
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            FeatureSeeder::class,
        ]);
    }
}
