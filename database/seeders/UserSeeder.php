<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * admin@example.com and staff@example.com are no longer seeded here.
     * They exist in the production dump and are imported via:
     *   php artisan import:production-data
     *
     * The owner@example.com and demo@example.com accounts are created by
     * the 2026_02_26_000007_seed_demo_and_owner migration.
     */
    public function run(): void
    {
        // No-op: real users come from production import; demo/owner from migrations.
    }
}
