<?php

namespace App\Repository;

use App\Helpers\JwtClaims;
use App\Models\ExpenseCategory;
use App\Traits\UsesCompanyScope;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository
{
    use UsesCompanyScope;

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

    public function findById(string $id): ?ExpenseCategory
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): ExpenseCategory
    {
        $data['company_id'] = $data['company_id'] ?? JwtClaims::companyId();
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
