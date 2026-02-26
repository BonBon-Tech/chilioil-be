<?php

namespace App\Repository;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ExpenseCategoryRepository
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
        $query = ExpenseCategory::query();
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function getAll(): Collection
    {
        return $this->scopedQuery()->get();
    }

    public function findById(int $id): ?ExpenseCategory
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): ExpenseCategory
    {
        $data['company_id'] = $data['company_id'] ?? Auth::user()->company_id;
        return ExpenseCategory::create($data);
    }

    public function update(ExpenseCategory $expenseCategory, array $data): bool
    {
        return $expenseCategory->update($data);
    }

    public function delete(ExpenseCategory $expenseCategory): bool
    {
        return $expenseCategory->delete();
    }
}
