<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlloDulDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $company = Company::updateOrCreate(
                ['slug' => 'allo-dul-local'],
                [
                    'name' => 'Allo Dul',
                    'is_demo' => false,
                    'plan' => Company::PLAN_PRO,
                    'subscription_expires_at' => Carbon::today()->addYear(),
                ]
            );
            $role = Role::firstOrCreate(
                ['name' => 'admin'],
                ['description' => 'Administrator']
            );
            User::updateOrCreate(
                ['email' => 'dul@example.com'],
                [
                    'name' => 'Pergi Jauh',
                    'company_id' => $company->id,
                    'store_id' => null,
                    'role_id' => $role->id,
                    'password' => 'dul123456',
                ]
            );
            $store = Store::updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Allo Dul Pusat'],
                ['logo' => null]
            );
            $category = ProductCategory::updateOrCreate(
                ['slug' => 'allo-dul-menu'],
                [
                    'company_id' => $company->id,
                    'name' => 'Menu Allo Dul',
                    'logo' => null,
                    'status' => true,
                ]
            );

            $products = collect([
                ['name' => 'Kopi Susu Allo', 'code' => 'DUL-KOPSU', 'price' => 18000],
                ['name' => 'Americano', 'code' => 'DUL-AMERICANO', 'price' => 15000],
                ['name' => 'Matcha Latte', 'code' => 'DUL-MATCHA', 'price' => 22000],
                ['name' => 'Croissant Butter', 'code' => 'DUL-CROISSANT', 'price' => 20000],
                ['name' => 'Rice Bowl Ayam', 'code' => 'DUL-RICEBOWL', 'price' => 28000],
            ])->map(fn (array $product) => Product::updateOrCreate(
                ['code' => $product['code']],
                [...$product, 'store_id' => $store->id, 'product_category_id' => $category->id, 'selling_type' => 'Sale', 'status' => true]
            ))->values();

            Transaction::withTrashed()
                ->where('company_id', $company->id)
                ->where('code', 'like', 'DEMO-DUL-%')
                ->forceDelete();

            $transactions = [];
            $items = [];
            $today = Carbon::today();
            $types = ['OFFLINE', 'OFFLINE', 'OFFLINE', 'GOFOOD', 'GRABFOOD', 'SHOPEEFOOD'];

            for ($day = 89; $day >= 0; $day--) {
                $date = $today->copy()->subDays($day);
                $dailyCount = 8 + ($day % 5) + ($date->isWeekend() ? 4 : 0);

                for ($sequence = 1; $sequence <= $dailyCount; $sequence++) {
                    $transactionId = (string) Str::uuid();
                    $type = $types[($day + $sequence) % count($types)];
                    $itemCount = 1 + (($day + $sequence) % 3);
                    $subTotal = 0;
                    $totalItems = 0;
                    $createdAt = $date->copy()->setTime(8 + (($sequence * 3) % 14), ($sequence * 7) % 60);

                    for ($itemIndex = 0; $itemIndex < $itemCount; $itemIndex++) {
                        $product = $products[($day + $sequence + $itemIndex) % $products->count()];
                        $quantity = 1 + (($sequence + $itemIndex) % 3);
                        $lineTotal = (float) $product->price * $quantity;
                        $subTotal += $lineTotal;
                        $totalItems += $quantity;
                        $items[] = [
                            'id' => (string) Str::uuid(),
                            'transaction_id' => $transactionId,
                            'product_id' => $product->id,
                            'store_id' => $store->id,
                            'name' => $product->name,
                            'code' => $product->code,
                            'image_path' => null,
                            'price' => $product->price,
                            'qty' => $quantity,
                            'total_price' => $lineTotal,
                            'note' => null,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                            'deleted_at' => null,
                        ];
                    }

                    $isOnline = $type !== 'OFFLINE';
                    $transactions[] = [
                        'id' => $transactionId,
                        'company_id' => $company->id,
                        'store_id' => $store->id,
                        'code' => sprintf('DEMO-DUL-%s-%02d', $date->format('Ymd'), $sequence),
                        'date' => $date->toDateString(),
                        'customer_name' => $sequence % 4 === 0 ? 'Pelanggan '.$sequence : null,
                        'total' => $subTotal,
                        'sub_total' => $subTotal,
                        'total_item' => $totalItems,
                        'type' => $type,
                        'payment_type' => $isOnline ? ['GOPAY', 'OVO', 'SHOPEEPAY'][$sequence % 3] : ($sequence % 3 === 0 ? 'QRIS' : 'CASH'),
                        'status' => $sequence % 23 === 0 ? 'CANCELED' : 'PAID',
                        'online_transaction_revenue' => $isOnline ? round($subTotal * 0.82, 2) : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => null,
                    ];
                }
            }

            foreach (array_chunk($transactions, 400) as $chunk) {
                DB::table('transactions')->insert($chunk);
            }
            foreach (array_chunk($items, 400) as $chunk) {
                DB::table('transaction_items')->insert($chunk);
            }
        });
    }
}
