<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        Store::firstOrCreate(['name' => 'Jajaneun ChiliOil'], ['logo' => null]);
        Store::firstOrCreate(['name' => 'Sate Nagihin'], ['logo' => null]);
        Store::firstOrCreate(['name' => 'Minuman'], ['logo' => null]);
    }
}

