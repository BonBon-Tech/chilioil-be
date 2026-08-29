<?php

namespace App\Repository;

use App\Helpers\JwtClaims;
use App\Models\CashLedgerEntry;
use App\Models\DailyReport;
use App\Models\DailyReportRestockItem;
use App\Models\Expense;
use App\Traits\UsesCompanyScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseRepository
{
    use UsesCompanyScope;

    public function __construct(private CashLedgerRepository $cash) {}

    private function scopedQuery()
    {
        $query = Expense::with('expenseCategory');
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public function getAll(): Collection
    {
        return $this->scopedQuery()->orderBy('date', 'desc')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->scopedQuery()->orderBy('date', 'desc');

        if (! empty($filters['search'])) {
            $query->where('description', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?Expense
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): Expense
    {
        $data['company_id'] = $data['company_id'] ?? JwtClaims::companyId();
        $allocations = $data['allocations'] ?? [];
        $creditorId = $data['creditor_id'] ?? null;
        unset($data['allocations'], $data['creditor_id']);

        return DB::transaction(function () use ($data, $allocations, $creditorId) {
            $expense = Expense::create($data);
            $report = DailyReport::firstOrCreate([
                'company_id' => $expense->company_id,
                'store_id' => $expense->store_id,
                'report_date' => $expense->date,
            ]);
            DailyReportRestockItem::create([
                'daily_report_id' => $report->id,
                'creditor_id' => $creditorId,
                'entry_type' => 'expense',
                'expense_id' => $expense->id,
                'expense_category_id' => $expense->expense_category_id,
                'name' => $expense->description ?: 'Pengeluaran',
                'amount' => $expense->amount,
            ]);
            if (! $creditorId && $allocations) {
                $this->cash->syncExpense($expense, $allocations, auth()->id());
            }

            return $expense->load('expenseCategory');
        });
    }

    public function update(Expense $expense, array $data): bool
    {
        $allocations = $data['allocations'] ?? [];
        $creditorId = $data['creditor_id'] ?? null;
        unset($data['allocations'], $data['creditor_id']);

        return DB::transaction(function () use ($expense, $data, $allocations, $creditorId) {
            $updated = $expense->update($data);
            $expense->refresh();
            $report = DailyReport::firstOrCreate([
                'company_id' => $expense->company_id,
                'store_id' => $expense->store_id,
                'report_date' => $expense->date,
            ]);
            DailyReportRestockItem::where('expense_id', $expense->id)->update([
                'daily_report_id' => $report->id,
                'creditor_id' => $creditorId,
                'expense_category_id' => $expense->expense_category_id,
                'name' => $expense->description ?: 'Pengeluaran',
                'amount' => $expense->amount,
            ]);
            if ($creditorId) {
                CashLedgerEntry::where('reference_type', 'expense')->where('reference_id', $expense->id)->delete();
            } else {
                $this->cash->syncExpense($expense, $allocations, auth()->id());
            }

            return $updated;
        });
    }

    public function delete(Expense $expense): bool
    {
        return DB::transaction(function () use ($expense) {
            CashLedgerEntry::where('reference_type', 'expense')->where('reference_id', $expense->id)->delete();
            DailyReportRestockItem::where('expense_id', $expense->id)->delete();

            return $expense->delete();
        });
    }
}
