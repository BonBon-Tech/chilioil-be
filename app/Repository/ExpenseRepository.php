<?php

namespace App\Repository;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;

class ExpenseRepository
{
    public function getAll(): Collection
    {
        return Expense::with('expenseCategory')->orderBy('date', 'desc')->get();
    }

    public function findById(int $id): ?Expense
    {
        return Expense::with('expenseCategory')->find($id);
    }

    public function create(array $data): Expense
    {
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

