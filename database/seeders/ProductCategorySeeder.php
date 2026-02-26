<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Chili Oil', 'slug' => 'chili-oil', 'status' => 1],
            ['name' => 'Sate', 'slug' => 'sate', 'status' => 1],
            ['name' => 'Minuman', 'slug' => 'minuman', 'status' => 1],
            ['name' => 'Snack', 'slug' => 'snack', 'status' => 1],
        ];

        foreach ($categories as $cat) {
            ProductCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'status' => $cat['status']]
            );
        }
    }
}
