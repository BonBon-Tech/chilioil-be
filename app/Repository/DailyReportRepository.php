<?php

namespace App\Repository;

use App\Models\DailyReport;
use App\Models\DailyReportCreditor;
use App\Models\DailyReportDebtPayment;
use App\Models\DailyReportRestockItem;
use App\Models\Expense;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyReportRepository
{
    public function createCreditor(string $companyId, array $data): DailyReportCreditor
    {
        return DailyReportCreditor::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function creditors(string $companyId, string $storeId)
    {
        return DailyReportCreditor::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function updateCreditor(string $companyId, string $storeId, string $id, array $data): DailyReportCreditor
    {
        $creditor = DailyReportCreditor::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->findOrFail($id);
        $creditor->update($data);

        return $creditor->refresh();
    }

    public function history(string $companyId, string $storeId, int $perPage): LengthAwarePaginator
    {
        $paginator = DailyReport::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->orderByDesc('report_date')
            ->paginate($perPage);

        // ponytail: bounded to 15 daily cards; batch aggregates if this endpoint becomes a hot path.
        return $paginator->through(fn (DailyReport $report) => $this->detail(
            $companyId,
            $storeId,
            $report->report_date->format('Y-m-d'),
        ));
    }

    public function summary(string $companyId, string $storeId): array
    {
        $overrides = DailyReport::query()
            ->where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->whereNotNull('shopeefood_amount')
            ->whereNotNull('grabfood_amount')
            ->whereNotNull('gofood_amount')
            ->whereNotNull('qris_amount')
            ->whereNotNull('cash_amount');

        $reported = (float) (clone $overrides)->sum(DB::raw(
            'shopeefood_amount + grabfood_amount + gofood_amount + qris_amount + cash_amount'
        ));
        $pos = (float) $this->incomeItemsQuery($companyId, $storeId)
            ->sum('transaction_items.total_price');
        $replacedPos = (float) $this->incomeItemsQuery($companyId, $storeId)
            ->whereIn(
                DB::raw('DATE(transactions.date)'),
                (clone $overrides)->selectRaw('DATE(report_date)'),
            )
            ->sum('transaction_items.total_price');
        $income = $pos - $replacedPos + $reported;
        $expense = (float) Expense::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->sum('amount');
        $debt = array_sum(array_column(
            $this->debtRows($companyId, $storeId, today()->toDateString()),
            'closing_balance',
        ));

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'debt' => $debt,
        ];
    }

    public function updateRestock(string $companyId, string $storeId, string $date, string $creditorId, array $items): array
    {
        DB::transaction(function () use ($companyId, $storeId, $date, $creditorId, $items) {
            $report = $this->report($companyId, $storeId, $date);

            $expenseIds = $report->restockItems()->pluck('expense_id')->filter();
            Expense::where('company_id', $companyId)->whereIn('id', $expenseIds)->delete();
            $report->restockItems()->delete();
            $report->update(['restock_creditor_id' => $items ? $creditorId : null]);

            if (! $items) {
                return;
            }

            $creditor = DailyReportCreditor::where('company_id', $companyId)->findOrFail($creditorId);

            foreach ($items as $item) {
                $description = trim(implode(' ', array_filter([
                    $item['name'],
                    $item['quantity'] ?? null,
                    $item['unit'] ?? null,
                ], fn ($value) => $value !== null && $value !== '')));

                $expense = Expense::create([
                    'company_id' => $companyId,
                    'store_id' => $storeId,
                    'expense_category_id' => $item['expense_category_id'],
                    'date' => $date,
                    'amount' => $item['amount'],
                    'reference' => $creditor->name,
                    'description' => $description,
                ]);

                DailyReportRestockItem::create([
                    ...$item,
                    'daily_report_id' => $report->id,
                    'expense_id' => $expense->id,
                ]);
            }
        });

        return $this->detail($companyId, $storeId, $date);
    }

    public function updateDebt(string $companyId, string $storeId, string $date, array $payments): array
    {
        DB::transaction(function () use ($companyId, $storeId, $date, $payments) {
            $report = $this->report($companyId, $storeId, $date);
            $submittedIds = collect($payments)->pluck('creditor_id');

            $report->debtPayments()->whereNotIn('creditor_id', $submittedIds)->delete();

            foreach ($payments as $payment) {
                $available = $this->balanceBeforePayment($companyId, $storeId, $date, $payment['creditor_id']);
                if ((float) $payment['amount'] > $available) {
                    throw ValidationException::withMessages([
                        'payments' => 'Pembayaran tidak boleh melebihi saldo utang tersedia.',
                    ]);
                }

                if ((float) $payment['amount'] === 0.0) {
                    $report->debtPayments()->where('creditor_id', $payment['creditor_id'])->delete();

                    continue;
                }

                DailyReportDebtPayment::updateOrCreate(
                    ['daily_report_id' => $report->id, 'creditor_id' => $payment['creditor_id']],
                    ['amount' => $payment['amount'], 'note' => $payment['note'] ?? null],
                );
            }
        });

        return $this->detail($companyId, $storeId, $date);
    }

    public function updateIncome(string $companyId, string $storeId, string $date, array $amounts): array
    {
        $report = $this->report($companyId, $storeId, $date);
        $report->update([
            'shopeefood_amount' => $amounts['shopeefood'],
            'grabfood_amount' => $amounts['grabfood'],
            'gofood_amount' => $amounts['gofood'],
            'qris_amount' => $amounts['qris'],
            'cash_amount' => $amounts['cash'],
        ]);

        return $this->detail($companyId, $storeId, $date);
    }

    public function detail(string $companyId, string $storeId, string $date): array
    {
        $report = DailyReport::with(['restockItems', 'restockCreditor'])
            ->where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->whereDate('report_date', $date)
            ->first();

        $items = $report?->restockItems->map(fn (DailyReportRestockItem $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $item->quantity === null ? null : (float) $item->quantity,
            'unit' => $item->unit,
            'expense_category_id' => $item->expense_category_id,
            'amount' => (float) $item->amount,
        ])->values()->all() ?? [];

        $source = $this->incomeSource($companyId, $storeId, $date);
        $reported = [];
        foreach (array_keys($source) as $channel) {
            $column = $channel.'_amount';
            $reported[$channel] = $report && $report->{$column} !== null
                ? (float) $report->{$column}
                : $source[$channel];
        }

        return [
            'id' => $report?->id,
            'report_date' => $date,
            'store_id' => $storeId,
            'restock' => [
                'creditor' => $report?->restockCreditor,
                'items' => $items,
                'total' => array_sum(array_column($items, 'amount')),
            ],
            'debt' => $this->debtRows($companyId, $storeId, $date),
            'income' => [
                'source' => $source,
                'reported' => $reported,
                'total' => array_sum($reported),
            ],
        ];
    }

    private function report(string $companyId, string $storeId, string $date): DailyReport
    {
        return DailyReport::firstOrCreate([
            'company_id' => $companyId,
            'store_id' => $storeId,
            'report_date' => $date,
        ]);
    }

    private function balanceBeforePayment(string $companyId, string $storeId, string $date, string $creditorId): float
    {
        $creditor = DailyReportCreditor::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->findOrFail($creditorId);

        if ($creditor->opening_date->format('Y-m-d') > $date) {
            return 0;
        }

        $restock = DailyReportRestockItem::query()
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_restock_items.daily_report_id')
            ->where('daily_reports.company_id', $companyId)
            ->where('daily_reports.store_id', $storeId)
            ->where('daily_reports.restock_creditor_id', $creditorId)
            ->whereDate('daily_reports.report_date', '<=', $date)
            ->whereNull('daily_report_restock_items.deleted_at')
            ->sum('daily_report_restock_items.amount');

        $paidBefore = DailyReportDebtPayment::query()
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_debt_payments.daily_report_id')
            ->where('daily_reports.company_id', $companyId)
            ->where('daily_reports.store_id', $storeId)
            ->where('daily_report_debt_payments.creditor_id', $creditorId)
            ->whereDate('daily_reports.report_date', '<', $date)
            ->sum('daily_report_debt_payments.amount');

        return (float) $creditor->opening_balance + (float) $restock - (float) $paidBefore;
    }

    private function debtRows(string $companyId, string $storeId, string $date): array
    {
        $creditors = DailyReportCreditor::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->whereDate('opening_date', '<=', $date)
            ->orderBy('name')
            ->get();

        $restockBefore = $this->restockTotals($companyId, $storeId, '<', $date);
        $restockToday = $this->restockTotals($companyId, $storeId, '=', $date);
        $paidBefore = $this->paymentTotals($companyId, $storeId, '<', $date);
        $paidToday = $this->paymentTotals($companyId, $storeId, '=', $date);

        return $creditors->map(function (DailyReportCreditor $creditor) use ($restockBefore, $restockToday, $paidBefore, $paidToday) {
            $id = $creditor->id;
            $opening = (float) $creditor->opening_balance
                + ($restockBefore[$id] ?? 0)
                - ($paidBefore[$id] ?? 0);
            $restock = $restockToday[$id] ?? 0;
            $payment = $paidToday[$id] ?? 0;

            return [
                'creditor_id' => $id,
                'name' => $creditor->name,
                'opening_balance' => $opening,
                'restock' => $restock,
                'payment' => $payment,
                'closing_balance' => $opening + $restock - $payment,
                'is_active' => $creditor->is_active,
            ];
        })->values()->all();
    }

    private function restockTotals(string $companyId, string $storeId, string $operator, string $date): array
    {
        return DailyReportRestockItem::query()
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_restock_items.daily_report_id')
            ->where('daily_reports.company_id', $companyId)
            ->where('daily_reports.store_id', $storeId)
            ->whereDate('daily_reports.report_date', $operator, $date)
            ->whereNull('daily_report_restock_items.deleted_at')
            ->groupBy('daily_reports.restock_creditor_id')
            ->selectRaw('daily_reports.restock_creditor_id, SUM(daily_report_restock_items.amount) AS total_amount')
            ->pluck('total_amount', 'daily_reports.restock_creditor_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function paymentTotals(string $companyId, string $storeId, string $operator, string $date): array
    {
        return DailyReportDebtPayment::query()
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_debt_payments.daily_report_id')
            ->where('daily_reports.company_id', $companyId)
            ->where('daily_reports.store_id', $storeId)
            ->whereDate('daily_reports.report_date', $operator, $date)
            ->groupBy('daily_report_debt_payments.creditor_id')
            ->selectRaw('daily_report_debt_payments.creditor_id, SUM(daily_report_debt_payments.amount) AS total_amount')
            ->pluck('total_amount', 'daily_report_debt_payments.creditor_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function incomeSource(string $companyId, string $storeId, string $date): array
    {
        $rows = $this->incomeItemsQuery($companyId, $storeId)
            ->whereDate('transactions.date', $date)
            ->groupBy('transactions.type', 'transactions.payment_type')
            ->selectRaw('transactions.type, transactions.payment_type, SUM(transaction_items.total_price) AS total')
            ->get();

        $source = ['shopeefood' => 0.0, 'grabfood' => 0.0, 'gofood' => 0.0, 'qris' => 0.0, 'cash' => 0.0];
        foreach ($rows as $row) {
            $channel = match ($row->type) {
                'SHOPEEFOOD' => 'shopeefood',
                'GRABFOOD' => 'grabfood',
                'GOFOOD' => 'gofood',
                'OFFLINE' => match ($row->payment_type) {
                    'QRIS' => 'qris',
                    'CASH' => 'cash',
                    default => null,
                },
                default => null,
            };
            if ($channel) {
                $source[$channel] += (float) $row->total;
            }
        }

        return $source;
    }

    private function incomeItemsQuery(string $companyId, string $storeId): Builder
    {
        return DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.company_id', $companyId)
            ->where('transaction_items.store_id', $storeId)
            ->where('transactions.status', 'PAID')
            ->whereNull('transactions.deleted_at')
            ->whereNull('transaction_items.deleted_at')
            ->where(function (Builder $query) {
                $query->whereIn('transactions.type', ['SHOPEEFOOD', 'GRABFOOD', 'GOFOOD'])
                    ->orWhere(function (Builder $query) {
                        $query->where('transactions.type', 'OFFLINE')
                            ->whereIn('transactions.payment_type', ['QRIS', 'CASH']);
                    });
            });
    }
}
