<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Repository\ExpenseRepository;
use App\Traits\CheckDemoLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use CheckDemoLimit;
    private ExpenseRepository $expenseRepository;

    public function __construct(ExpenseRepository $expenseRepository)
    {
        $this->expenseRepository = $expenseRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $expenses = $this->expenseRepository->paginate($perPage, $request->all());
        return ApiResponse::success($expenses, 'Expenses retrieved successfully');
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $demoCheck = $this->checkDemoLimit(Expense::class, 10);
        if ($demoCheck) return $demoCheck;

        $expense = $this->expenseRepository->create($request->validated());
        return ApiResponse::success($expense, 'Expense created successfully');
    }

    public function show(string $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (!$expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        return ApiResponse::success($expense, 'Expense retrieved successfully');
    }

    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (!$expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        $this->expenseRepository->update($expense, $request->validated());
        $expense->refresh()->load('expenseCategory');

        return ApiResponse::success($expense, 'Expense updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (!$expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        $this->expenseRepository->delete($expense);
        return ApiResponse::success(null, 'Expense deleted successfully');
    }
}
