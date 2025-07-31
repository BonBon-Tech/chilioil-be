<?php

namespace App\Repository;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository
{
    public function getAll(): Collection
    {
        return ExpenseCategory::all();
    }

    public function findById(int $id): ?ExpenseCategory
    {
        return ExpenseCategory::find($id);
    }

    public function create(array $data): ExpenseCategory
    {
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

