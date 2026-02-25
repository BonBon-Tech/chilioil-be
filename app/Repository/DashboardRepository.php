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
    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $transactionQuery = Transaction::query();
        $onlineQuery = Transaction::query();
        $expenseQuery = Expense::query();

        if ($startDate && $endDate) {
            $transactionQuery->whereBetween('date', [$startDate, $endDate]);
            $onlineQuery->whereBetween('date', [$startDate, $endDate]);
            $expenseQuery->whereBetween('date', [$startDate, $endDate]);
        }

        return [
            'product_count' => Product::count(),
            'user_count' => User::count(),
            'transaction_total' => (float) (clone $transactionQuery)->where('type', '=', 'offline')->where('status', '=', 'PAID')->sum('total'),
            'online_transaction_total' => (float) (clone $onlineQuery)->where('type', '!=', 'offline')->sum('online_transaction_revenue'),
            'expense_total' => (float) $expenseQuery->sum('amount'),
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
     */
    public function getStoreSales(?string $startDate = null, ?string $endDate = null): array
    {
        $query = TransactionItem::selectRaw('store_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                $q->where('type', 'offline')->where('status', 'PAID');
                if ($startDate && $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                }
            })
            ->groupBy('store_id')
            ->with('store')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('store_id');

        $stores = Store::all();

        $result = $stores->map(function ($store) use ($query) {
            $sale = $query->get($store->id);
            return [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total_qty' => $sale ? (int) $sale->total_qty : 0,
                'total_sales' => $sale ? (float) $sale->total_sales : 0.0,
            ];
        });

        return $result->sortByDesc('total_sales')->values()->toArray();
    }

    /**
     * Get online sales summary per store.
     */
    public function getOnlineStoreSales(?string $startDate = null, ?string $endDate = null): array
    {
        $sales = OnlineTransactionDetail::selectRaw('store_id, SUM(revenue) as total_sales')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                $q->where('type', '!=', 'offline')->where('status', 'PAID');
                if ($startDate && $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                }
            })
            ->groupBy('store_id')
            ->with('store')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('store_id');

        $stores = Store::all();

        $result = $stores->map(function ($store) use ($sales) {
            $sale = $sales->get($store->id);
            return [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total_qty' => $sale ? (int) ($sale->total_qty ?? 0) : 0,
                'total_sales' => $sale ? (float) $sale->total_sales : 0.0,
            ];
        });

        return $result->sortByDesc('total_sales')->values()->toArray();
    }

    public function getDailyOfflineSales(?string $startDate = null, ?string $endDate = null): float
    {
        $query = Transaction::query()
            ->where('type', '=', 'offline')
            ->where('status', '=', 'PAID');

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            $query->whereDate('date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        }

        return (float) $query->sum('total');
    }

    public function getDailyOnlineSales(?string $startDate = null, ?string $endDate = null): float
    {
        $query = Transaction::query()
            ->where('type', '!=', 'offline')
            ->where('status', '=', 'PAID');

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            $query->whereDate('date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        }

        return (float) $query->sum('online_transaction_revenue');
    }

    /**
     * Get weekly sales traffic (per-day totals).
     */
    public function getWeeklyTraffic(string $startDate, string $endDate): array
    {
        $results = Transaction::selectRaw('DATE(date) as date, SUM(total) as total_sales, COUNT(*) as total_transactions')
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupByRaw('DATE(date)')
            ->orderBy('date')
            ->get();

        return $results->map(function ($row) {
            return [
                'date' => $row->date,
                'total_sales' => (float) $row->total_sales,
                'total_transactions' => (int) $row->total_transactions,
            ];
        })->toArray();
    }

    /**
     * Get top products by qty sold.
     */
    public function getTopProducts(string $startDate, string $endDate, int $limit = 10): array
    {
        $results = TransactionItem::selectRaw('product_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'PAID')->whereBetween('date', [$startDate, $endDate]);
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();

        return $results->map(function ($row) {
            return [
                'product_id' => $row->product_id,
                'product_name' => $row->product?->name ?? 'Unknown',
                'image_url' => $row->product?->image_url,
                'total_qty' => (int) $row->total_qty,
                'total_sales' => (float) $row->total_sales,
            ];
        })->toArray();
    }
}
