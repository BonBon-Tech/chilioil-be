<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('name', 'Jajaneun ChiliOil')->first();
        $sateStore = Store::where('name', 'Sate Nagihin')->first();
        $minumanStore = Store::where('name', 'Minuman')->first();

        $chiliOilCat = ProductCategory::where('slug', 'chili-oil')->first();
        $sateCat = ProductCategory::where('slug', 'sate')->first();
        $minumanCat = ProductCategory::where('slug', 'minuman')->first();
        $snackCat = ProductCategory::where('slug', 'snack')->first();

        $products = [
            // Chili Oil Products
            ['name' => 'Chili Oil Original 150ml', 'code' => 'CO-ORI-150', 'price' => 25000, 'store_id' => $store?->id ?? 1, 'product_category_id' => $chiliOilCat?->id ?? 1, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Chili Oil Extra Pedas 150ml', 'code' => 'CO-XP-150', 'price' => 28000, 'store_id' => $store?->id ?? 1, 'product_category_id' => $chiliOilCat?->id ?? 1, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Chili Oil Bawang 150ml', 'code' => 'CO-BWG-150', 'price' => 27000, 'store_id' => $store?->id ?? 1, 'product_category_id' => $chiliOilCat?->id ?? 1, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Chili Oil Original 250ml', 'code' => 'CO-ORI-250', 'price' => 40000, 'store_id' => $store?->id ?? 1, 'product_category_id' => $chiliOilCat?->id ?? 1, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Chili Oil Gift Set', 'code' => 'CO-GIFT', 'price' => 85000, 'store_id' => $store?->id ?? 1, 'product_category_id' => $chiliOilCat?->id ?? 1, 'selling_type' => 'Sale', 'status' => 1],

            // Sate Products
            ['name' => 'Sate Ayam 10 Tusuk', 'code' => 'SA-AYM-10', 'price' => 16000, 'store_id' => $sateStore?->id ?? 2, 'product_category_id' => $sateCat?->id ?? 2, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Sate Kulit 10 Tusuk', 'code' => 'SA-KLT-10', 'price' => 14000, 'store_id' => $sateStore?->id ?? 2, 'product_category_id' => $sateCat?->id ?? 2, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Sate Taichan 10 Tusuk', 'code' => 'SA-TCH-10', 'price' => 18000, 'store_id' => $sateStore?->id ?? 2, 'product_category_id' => $sateCat?->id ?? 2, 'selling_type' => 'Sale', 'status' => 1],

            // Minuman
            ['name' => 'Es Teh Manis', 'code' => 'MN-ETM', 'price' => 5000, 'store_id' => $minumanStore?->id ?? 3, 'product_category_id' => $minumanCat?->id ?? 3, 'selling_type' => 'Sale', 'status' => 1],
            ['name' => 'Es Jeruk', 'code' => 'MN-EJR', 'price' => 7000, 'store_id' => $minumanStore?->id ?? 3, 'product_category_id' => $minumanCat?->id ?? 3, 'selling_type' => 'Sale', 'status' => 1],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['code' => $product['code']],
                $product
            );
        }
    }
}
