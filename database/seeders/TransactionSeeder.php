<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->warn('No products found. Run ProductSeeder first.');
            return;
        }

        $paymentTypes = ['CASH', 'QRIS'];
        $now = Carbon::now();

        for ($i = 0; $i < 50; $i++) {
            $date = $now->copy()->subDays(rand(0, 30));
            $itemCount = rand(1, 4);
            $selectedProducts = $products->random(min($itemCount, $products->count()));

            $subTotal = 0;
            $totalItems = 0;
            $items = [];

            foreach ($selectedProducts as $product) {
                $qty = rand(1, 5);
                $price = (float) $product->price;
                $totalPrice = $price * $qty;
                $subTotal += $totalPrice;
                $totalItems += $qty;

                $items[] = [
                    'product_id' => $product->id,
                    'store_id' => $product->store_id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'image_path' => $product->image_path,
                    'price' => $price,
                    'qty' => $qty,
                    'total_price' => $totalPrice,
                ];
            }

            $transaction = Transaction::create([
                'code' => 'TRX-' . $date->format('Ymd') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'date' => $date->toDateString(),
                'customer_name' => null,
                'total' => $subTotal,
                'sub_total' => $subTotal,
                'total_item' => $totalItems,
                'type' => 'offline',
                'payment_type' => $paymentTypes[array_rand($paymentTypes)],
                'status' => 'PAID',
            ]);

            foreach ($items as $item) {
                $item['transaction_id'] = $transaction->id;
                TransactionItem::create($item);
            }
        }
    }
}
