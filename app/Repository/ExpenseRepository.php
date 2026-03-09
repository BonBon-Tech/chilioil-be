<?php

namespace App\Repository;

use App\Helpers\JwtClaims;
use App\Models\Expense;
use App\Traits\UsesCompanyScope;
use Illuminate\Database\Eloquent\Collection;

class ExpenseRepository
{
    use UsesCompanyScope;

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

        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
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
        $expense = Expense::create($data);
        return $expense->load('expenseCategory');
    }

    public function update(Expense $expense, array $data): bool
    {
        return $expense->update($data);
    }

    public function delete(Expense $expense): bool
    {
        return $expense->delete();
    }
}
