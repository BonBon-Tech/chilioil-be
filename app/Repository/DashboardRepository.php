<?php

namespace App\Repository;

use App\Models\OnlineTransactionDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Expense;
use App\Models\TransactionItem;
use App\Models\Store;
use Carbon\Carbon;

class DashboardRepository
{
    public function getSummary(): array
    {
        return [
            'product_count' => Product::count(),
            'user_count' => User::count(),
            'transaction_total' => (float) Transaction::where('type', '=', 'offline')->where('status', '=', 'PAID')->sum('total'),
            'online_transaction_total' => (float) Transaction::where('type', '!=', 'offline')->sum('online_transaction_revenue'),
            'expense_total' => (float) Expense::sum('amount'),
        ];
    }

    /**
     * @param int|null $storeId
     * @return \Illuminate\Support\Collection
     */
    public function getProductSales($storeId = null)
    {
        $query = TransactionItem::selectRaw('product_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) {
                $q->where('status', 'PAID');
            })
            ->with('product')
            ->groupBy('product_id');

        if ($storeId) {
            $query->whereHas('product', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        // Sort by total_sales desc
        $query->orderByDesc('total_sales');

        return $query->get();
    }

    /**
     * Get sales summary per store, including stores with no transactions.
     *
     * @return array
     */
    public function getStoreSales(): array
    {
        // Get sales data for stores with transactions
        $sales = TransactionItem::selectRaw('store_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) {
                $q->where('type', 'offline')->where('status', 'PAID');
            })
            ->groupBy('store_id')
            ->with('store')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('store_id');

        // Get all stores
        $stores = Store::all();

        // Map all stores, filling in zeroes for those without sales
        $result = $stores->map(function ($store) use ($sales) {
            $sale = $sales->get($store->id);
            return [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total_qty' => $sale ? (int) $sale->total_qty : 0,
                'total_sales' => $sale ? (float) $sale->total_sales : 0.0,
            ];
        });

        // Sort by total_sales desc
        $result = $result->sortByDesc('total_sales')->values()->toArray();

        return $result;
    }

    /**
     * Get sales summary per store, including stores with no transactions.
     *
     * @return array
     */
    public function getOnlineStoreSales(): array
    {
        // Get sales data for stores with transactions
        $sales = OnlineTransactionDetail::selectRaw('store_id, SUM(revenue) as total_sales')
            ->whereHas('transaction', function ($q) {
                $q->where('type', '!=','offline')->where('status', 'PAID');
            })
            ->groupBy('store_id')
            ->with('store')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('store_id');

        // Get all stores
        $stores = Store::all();

        // Map all stores, filling in zeroes for those without sales
        $result = $stores->map(function ($store) use ($sales) {
            $sale = $sales->get($store->id);
            return [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total_qty' => $sale ? (int) $sale->total_qty : 0,
                'total_sales' => $sale ? (float) $sale->total_sales : 0.0,
            ];
        });

        // Sort by total_sales desc
        $result = $result->sortByDesc('total_sales')->values()->toArray();

        return $result;
    }

    public function getDailyOfflineSales(): float
    {
        return (float) Transaction::query()
            ->where('type', '=', 'offline')
            ->where('status', '=', 'PAID')
            ->whereDate('date', Carbon::now('Asia/Jakarta')->format('Y-m-d'))
            ->sum('total');
    }

    public function getDailyOnlineSales(): float
    {
        return (float) Transaction::query()
            ->where('type', '!=', 'offline')
            ->where('status', '=', 'PAID')
            ->whereDate('date', Carbon::now('Asia/Jakarta')->format('Y-m-d'))
            ->sum('online_transaction_revenue');
    }
}
