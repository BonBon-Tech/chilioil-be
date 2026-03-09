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

        $anyFirstCat = ProductCategory::first();
        $chiliOilCat = ProductCategory::where('slug', 'chili-oil')->first() ?? $anyFirstCat;
        $sateCat = ProductCategory::where('slug', 'sate')->first() ?? $anyFirstCat;
        $minumanCat = ProductCategory::where('slug', 'minuman')->first() ?? $anyFirstCat;
        $snackCat = ProductCategory::where('slug', 'snack')->first() ?? $anyFirstCat;

        // Group products per store so we skip them if store or category is missing
        $groups = [];

        if ($store && $chiliOilCat) {
            $groups[] = [
                'store_id' => $store->id,
                'product_category_id' => $chiliOilCat->id,
                'items' => [
                    ['name' => 'Chili Oil Original 150ml',  'code' => 'CO-ORI-150',  'price' => 25000,  'selling_type' => 'Sale'],
                    ['name' => 'Chili Oil Extra Pedas 150ml','code' => 'CO-XP-150',   'price' => 28000,  'selling_type' => 'Sale'],
                    ['name' => 'Chili Oil Bawang 150ml',    'code' => 'CO-BWG-150',  'price' => 27000,  'selling_type' => 'Sale'],
                    ['name' => 'Chili Oil Original 250ml',  'code' => 'CO-ORI-250',  'price' => 40000,  'selling_type' => 'Sale'],
                    ['name' => 'Chili Oil Gift Set',        'code' => 'CO-GIFT',     'price' => 85000,  'selling_type' => 'Sale'],
                    ['name' => 'Cabai Rawit Merah (kg)',    'code' => 'BB-CRM',      'price' => 50000,  'selling_type' => 'Purchase'],
                    ['name' => 'Minyak Goreng (liter)',     'code' => 'BB-MYK',      'price' => 18000,  'selling_type' => 'Purchase'],
                    ['name' => 'Bawang Putih (kg)',         'code' => 'BB-BWP',      'price' => 35000,  'selling_type' => 'Purchase'],
                    ['name' => 'Kemasan Botol 150ml',       'code' => 'BB-BTL150',   'price' => 2500,   'selling_type' => 'Purchase'],
                ],
            ];
        }

        if ($sateStore && $sateCat) {
            $groups[] = [
                'store_id' => $sateStore->id,
                'product_category_id' => $sateCat->id,
                'items' => [
                    ['name' => 'Sate Ayam 10 Tusuk',   'code' => 'SA-AYM-10', 'price' => 16000, 'selling_type' => 'Sale'],
                    ['name' => 'Sate Kulit 10 Tusuk',  'code' => 'SA-KLT-10', 'price' => 14000, 'selling_type' => 'Sale'],
                    ['name' => 'Sate Taichan 10 Tusuk','code' => 'SA-TCH-10', 'price' => 18000, 'selling_type' => 'Sale'],
                    ['name' => 'Ayam Potong (kg)',      'code' => 'BB-AYM',    'price' => 38000, 'selling_type' => 'Purchase'],
                    ['name' => 'Tusuk Sate (100 pcs)', 'code' => 'BB-TSK',    'price' => 5000,  'selling_type' => 'Purchase'],
                    ['name' => 'Kecap Manis (liter)',   'code' => 'BB-KCP',    'price' => 22000, 'selling_type' => 'Purchase'],
                ],
            ];
        }

        if ($minumanStore && $minumanCat) {
            $groups[] = [
                'store_id' => $minumanStore->id,
                'product_category_id' => $minumanCat->id,
                'items' => [
                    ['name' => 'Es Teh Manis',      'code' => 'MN-ETM',  'price' => 5000,  'selling_type' => 'Sale'],
                    ['name' => 'Es Jeruk',           'code' => 'MN-EJR',  'price' => 7000,  'selling_type' => 'Sale'],
                    ['name' => 'Teh Celup (box)',    'code' => 'BB-TCL',  'price' => 12000, 'selling_type' => 'Purchase'],
                    ['name' => 'Jeruk Segar (kg)',   'code' => 'BB-JRK',  'price' => 20000, 'selling_type' => 'Purchase'],
                    ['name' => 'Gula Pasir (kg)',    'code' => 'BB-GLA',  'price' => 14000, 'selling_type' => 'Purchase'],
                ],
            ];
        }

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                Product::firstOrCreate(
                    ['code' => $item['code']],
                    array_merge($item, [
                        'store_id'            => $group['store_id'],
                        'product_category_id' => $group['product_category_id'],
                        'status'              => 1,
                    ])
                );
            }
        }
    }
}
