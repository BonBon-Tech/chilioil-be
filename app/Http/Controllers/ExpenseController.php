<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Repository\ExpenseRepository;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    private ExpenseRepository $expenseRepository;

    public function __construct(ExpenseRepository $expenseRepository)
    {
        $this->expenseRepository = $expenseRepository;
    }

    public function index(): JsonResponse
    {
        $expenses = $this->expenseRepository->getAll();
        return ApiResponse::success($expenses, 'Expenses retrieved successfully');
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseRepository->create($request->validated());
        return ApiResponse::success($expense, 'Expense created successfully');
    }

    public function show(int $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (!$expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        return ApiResponse::success($expense, 'Expense retrieved successfully');
    }

    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (!$expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        $this->expenseRepository->update($expense, $request->validated());
        $expense->refresh()->load('expenseCategory');

        return ApiResponse::success($expense, 'Expense updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (!$expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        $this->expenseRepository->delete($expense);
        return ApiResponse::success(null, 'Expense deleted successfully');
    }
}
