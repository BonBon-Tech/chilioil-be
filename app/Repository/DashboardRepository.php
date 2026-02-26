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
use Illuminate\Support\Facades\Auth;

class DashboardRepository
{
    private function getCompanyId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->role && $user->role->name === 'owner') {
            return null;
        }
        return $user?->company_id;
    }

    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $companyId = $this->getCompanyId();

        $transactionQuery = Transaction::query();
        $onlineQuery = Transaction::query();
        $expenseQuery = Expense::query();
        $productQuery = Product::query();
        $userQuery = User::query();

        if ($companyId) {
            $transactionQuery->where('company_id', $companyId);
            $onlineQuery->where('company_id', $companyId);
            $expenseQuery->where('company_id', $companyId);
            $productQuery->whereHas('store', fn($q) => $q->where('company_id', $companyId));
            $userQuery->where('company_id', $companyId);
        }

        if ($startDate && $endDate) {
            $transactionQuery->whereBetween('date', [$startDate, $endDate]);
            $onlineQuery->whereBetween('date', [$startDate, $endDate]);
            $expenseQuery->whereBetween('date', [$startDate, $endDate]);
        }

        return [
            'product_count' => $productQuery->count(),
            'user_count' => $userQuery->count(),
            'transaction_total' => (float) (clone $transactionQuery)->where('type', '=', 'offline')->where('status', '=', 'PAID')->sum('total'),
            'online_transaction_total' => (float) (clone $onlineQuery)->where('type', '!=', 'offline')->sum('online_transaction_revenue'),
            'expense_total' => (float) $expenseQuery->sum('amount'),
        ];
    }

    public function getProductSales($storeId = null)
    {
        $companyId = $this->getCompanyId();

        $query = TransactionItem::selectRaw('product_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) use ($companyId) {
                $q->where('status', 'PAID');
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->with('product')
            ->groupBy('product_id');

        if ($storeId) {
            $query->whereHas('product', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        $query->orderByDesc('total_sales');

        return $query->get();
    }

    public function getStoreSales(?string $startDate = null, ?string $endDate = null): array
    {
        $companyId = $this->getCompanyId();

        $query = TransactionItem::selectRaw('store_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate, $companyId) {
                $q->where('type', 'offline')->where('status', 'PAID');
                if ($startDate && $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                }
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->groupBy('store_id')
            ->with('store')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('store_id');

        $storesQuery = Store::query();
        if ($companyId) {
            $storesQuery->where('company_id', $companyId);
        }
        $stores = $storesQuery->get();

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

    public function getOnlineStoreSales(?string $startDate = null, ?string $endDate = null): array
    {
        $companyId = $this->getCompanyId();

        $sales = OnlineTransactionDetail::selectRaw('store_id, SUM(revenue) as total_sales')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate, $companyId) {
                $q->where('type', '!=', 'offline')->where('status', 'PAID');
                if ($startDate && $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                }
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->groupBy('store_id')
            ->with('store')
            ->orderByDesc('total_sales')
            ->get()
            ->keyBy('store_id');

        $storesQuery = Store::query();
        if ($companyId) {
            $storesQuery->where('company_id', $companyId);
        }
        $stores = $storesQuery->get();

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
        $companyId = $this->getCompanyId();

        $query = Transaction::query()
            ->where('type', '=', 'offline')
            ->where('status', '=', 'PAID');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            $query->whereDate('date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        }

        return (float) $query->sum('total');
    }

    public function getDailyOnlineSales(?string $startDate = null, ?string $endDate = null): float
    {
        $companyId = $this->getCompanyId();

        $query = Transaction::query()
            ->where('type', '!=', 'offline')
            ->where('status', '=', 'PAID');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } else {
            $query->whereDate('date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        }

        return (float) $query->sum('online_transaction_revenue');
    }

    public function getWeeklyTraffic(string $startDate, string $endDate): array
    {
        $companyId = $this->getCompanyId();

        $query = Transaction::selectRaw('DATE(date) as date, SUM(total) as total_sales, COUNT(*) as total_transactions')
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $results = $query->groupByRaw('DATE(date)')
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

    public function getTopProducts(string $startDate, string $endDate, int $limit = 10): array
    {
        $companyId = $this->getCompanyId();

        $query = TransactionItem::selectRaw('product_id, SUM(qty) as total_qty, SUM(total_price) as total_sales')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate, $companyId) {
                $q->where('status', 'PAID')->whereBetween('date', [$startDate, $endDate]);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit($limit);

        $results = $query->get();

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
