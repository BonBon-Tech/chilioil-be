<?php

namespace App\Repository;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ExpenseRepository
{
    private function getCompanyId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->role && $user->role->name === 'owner') {
            return null;
        }
        return $user?->company_id;
    }

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

        if (isset($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Expense
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): Expense
    {
        $data['company_id'] = $data['company_id'] ?? Auth::user()->company_id;
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
