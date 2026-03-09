<?php

namespace App\Repository;

use App\Models\Expense;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Traits\UsesCompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    use UsesCompanyScope;

    // ──────────────────────────────────────────────────────────────────
    // 1. Sales Summary (period: daily | weekly | monthly)
    // ──────────────────────────────────────────────────────────────────
    public function getSalesSummary(
        string $period,
        string $startDate,
        string $endDate,
        ?string $storeId = null
    ): array {
        $companyId = $this->getCompanyId();

        $query = Transaction::query()
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        // Grouping by period
        switch ($period) {
            case 'daily':
                $select  = "DATE(`date`) as period_label, COUNT(*) as transaction_count, SUM(total) as total_revenue";
                $groupBy = "DATE(`date`)";
                $orderBy = "period_label";
                break;
            case 'weekly':
                $select  = "YEARWEEK(`date`, 1) as period_key, MIN(DATE(`date`)) as period_label, COUNT(*) as transaction_count, SUM(total) as total_revenue";
                $groupBy = "YEARWEEK(`date`, 1)";
                $orderBy = "period_key";
                break;
            default: // monthly
                $select  = "DATE_FORMAT(`date`, '%Y-%m') as period_label, COUNT(*) as transaction_count, SUM(total) as total_revenue";
                $groupBy = "DATE_FORMAT(`date`, '%Y-%m')";
                $orderBy = "period_label";
        }

        $rows = $query->selectRaw($select)
            ->groupByRaw($groupBy)
            ->orderByRaw($orderBy)
            ->get();

        return $rows->map(function ($row) {
            $count   = (int) $row->transaction_count;
            $revenue = (float) $row->total_revenue;
            return [
                'period_label'        => $row->period_label,
                'transaction_count'   => $count,
                'total_revenue'       => $revenue,
                'avg_per_transaction' => $count > 0 ? round($revenue / $count, 2) : 0,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. Sales by Type (OFFLINE / SHOPEEFOOD / GOFOOD / GRABFOOD)
    // ──────────────────────────────────────────────────────────────────
    public function getSalesByType(
        string $startDate,
        string $endDate,
        ?string $storeId = null
    ): array {
        $companyId = $this->getCompanyId();

        $query = Transaction::selectRaw(
                'type, COUNT(*) as transaction_count, SUM(total) as total_revenue'
            )
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $rows  = $query->groupBy('type')->orderByDesc('total_revenue')->get();
        $grand = $rows->sum('total_revenue');

        return $rows->map(function ($row) use ($grand) {
            return [
                'type'              => $row->type,
                'transaction_count' => (int) $row->transaction_count,
                'total_revenue'     => (float) $row->total_revenue,
                'percentage'        => $grand > 0 ? round(($row->total_revenue / $grand) * 100, 2) : 0,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. Sales by Payment Type
    // ──────────────────────────────────────────────────────────────────
    public function getSalesByPayment(
        string $startDate,
        string $endDate,
        ?string $storeId = null
    ): array {
        $companyId = $this->getCompanyId();

        $query = Transaction::selectRaw(
                'payment_type, COUNT(*) as transaction_count, SUM(total) as total_revenue'
            )
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $rows  = $query->groupBy('payment_type')->orderByDesc('total_revenue')->get();
        $grand = $rows->sum('total_revenue');

        return $rows->map(function ($row) use ($grand) {
            return [
                'payment_type'      => $row->payment_type,
                'transaction_count' => (int) $row->transaction_count,
                'total_revenue'     => (float) $row->total_revenue,
                'percentage'        => $grand > 0 ? round(($row->total_revenue / $grand) * 100, 2) : 0,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. Sales by Store
    // ──────────────────────────────────────────────────────────────────
    public function getSalesByStore(string $startDate, string $endDate): array
    {
        $companyId = $this->getCompanyId();

        $query = Transaction::selectRaw(
                'store_id, COUNT(*) as transaction_count, SUM(total) as total_revenue'
            )
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $rows = $query->with('store')
            ->groupBy('store_id')
            ->orderByDesc('total_revenue')
            ->get();

        return $rows->map(function ($row) {
            return [
                'store_id'          => $row->store_id,
                'store_name'        => $row->store?->name ?? 'Semua Toko',
                'transaction_count' => (int) $row->transaction_count,
                'total_revenue'     => (float) $row->total_revenue,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 5. Top Products
    // ──────────────────────────────────────────────────────────────────
    public function getTopProducts(
        string $startDate,
        string $endDate,
        ?string $storeId = null,
        int $limit = 10
    ): array {
        $companyId = $this->getCompanyId();

        $query = TransactionItem::selectRaw(
                'product_id, SUM(qty) as total_qty, SUM(total_price) as total_revenue'
            )
            ->whereHas('transaction', function ($q) use ($startDate, $endDate, $storeId, $companyId) {
                $q->where('status', 'PAID')->whereBetween('date', [$startDate, $endDate]);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit($limit);

        $rows  = $query->get();
        $grand = $rows->sum('total_revenue');

        return $rows->map(function ($row) use ($grand) {
            return [
                'product_id'    => $row->product_id,
                'product_name'  => $row->product?->name ?? 'Unknown',
                'total_qty'     => (int) $row->total_qty,
                'total_revenue' => (float) $row->total_revenue,
                'percentage'    => $grand > 0 ? round(($row->total_revenue / $grand) * 100, 2) : 0,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 6. Expense Summary (period: daily | weekly | monthly)
    // ──────────────────────────────────────────────────────────────────
    public function getExpenseSummary(
        string $period,
        string $startDate,
        string $endDate,
        ?string $storeId = null
    ): array {
        $companyId = $this->getCompanyId();

        $query = Expense::query()
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        switch ($period) {
            case 'daily':
                $select  = "DATE(`date`) as period_label, COUNT(*) as expense_count, SUM(amount) as total_amount";
                $groupBy = "DATE(`date`)";
                $orderBy = "period_label";
                break;
            case 'weekly':
                $select  = "YEARWEEK(`date`, 1) as period_key, MIN(DATE(`date`)) as period_label, COUNT(*) as expense_count, SUM(amount) as total_amount";
                $groupBy = "YEARWEEK(`date`, 1)";
                $orderBy = "period_key";
                break;
            default: // monthly
                $select  = "DATE_FORMAT(`date`, '%Y-%m') as period_label, COUNT(*) as expense_count, SUM(amount) as total_amount";
                $groupBy = "DATE_FORMAT(`date`, '%Y-%m')";
                $orderBy = "period_label";
        }

        $rows = $query->selectRaw($select)
            ->groupByRaw($groupBy)
            ->orderByRaw($orderBy)
            ->get();

        return $rows->map(function ($row) {
            return [
                'period_label'  => $row->period_label,
                'expense_count' => (int) $row->expense_count,
                'total_amount'  => (float) $row->total_amount,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 7. Expense by Category
    // ──────────────────────────────────────────────────────────────────
    public function getExpenseByCategory(
        string $startDate,
        string $endDate,
        ?string $storeId = null
    ): array {
        $companyId = $this->getCompanyId();

        $query = Expense::selectRaw(
                'expense_category_id, COUNT(*) as expense_count, SUM(amount) as total_amount'
            )
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $rows  = $query->with('expenseCategory')
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->get();
        $grand = $rows->sum('total_amount');

        return $rows->map(function ($row) use ($grand) {
            return [
                'category_id'   => $row->expense_category_id,
                'category_name' => $row->expenseCategory?->name ?? 'Tanpa Kategori',
                'expense_count' => (int) $row->expense_count,
                'total_amount'  => (float) $row->total_amount,
                'percentage'    => $grand > 0 ? round(($row->total_amount / $grand) * 100, 2) : 0,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 8. Profit & Loss (monthly grouping)
    // ──────────────────────────────────────────────────────────────────
    public function getProfitLoss(
        string $startDate,
        string $endDate,
        ?string $storeId = null
    ): array {
        $companyId = $this->getCompanyId();

        // Sales per month
        $salesQuery = Transaction::selectRaw(
                "DATE_FORMAT(`date`, '%Y-%m') as period_label, SUM(total) as total_sales"
            )
            ->where('status', 'PAID')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $salesQuery->where('company_id', $companyId);
        }
        if ($storeId) {
            $salesQuery->where('store_id', $storeId);
        }

        $salesRows = $salesQuery
            ->groupByRaw("DATE_FORMAT(`date`, '%Y-%m')")
            ->orderBy('period_label')
            ->get()
            ->keyBy('period_label');

        // Expenses per month
        $expQuery = Expense::selectRaw(
                "DATE_FORMAT(`date`, '%Y-%m') as period_label, SUM(amount) as total_expenses"
            )
            ->whereBetween('date', [$startDate, $endDate]);

        if ($companyId) {
            $expQuery->where('company_id', $companyId);
        }
        if ($storeId) {
            $expQuery->where('store_id', $storeId);
        }

        $expRows = $expQuery
            ->groupByRaw("DATE_FORMAT(`date`, '%Y-%m')")
            ->orderBy('period_label')
            ->get()
            ->keyBy('period_label');

        // Merge periods
        $periods = collect($salesRows->keys())->merge($expRows->keys())->unique()->sort()->values();

        return $periods->map(function ($period) use ($salesRows, $expRows) {
            $sales    = (float) ($salesRows->get($period)?->total_sales ?? 0);
            $expenses = (float) ($expRows->get($period)?->total_expenses ?? 0);
            $profit   = $sales - $expenses;

            return [
                'period_label'     => $period,
                'total_sales'      => $sales,
                'total_expenses'   => $expenses,
                'profit'           => $profit,
                'margin_percentage' => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0,
            ];
        })->toArray();
    }
}
