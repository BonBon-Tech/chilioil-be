<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Repository\ExpenseCategoryRepository;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    private ExpenseCategoryRepository $expenseCategoryRepository;

    public function __construct(ExpenseCategoryRepository $expenseCategoryRepository)
    {
        $this->expenseCategoryRepository = $expenseCategoryRepository;
    }

    public function index(): JsonResponse
    {
        $expenseCategories = $this->expenseCategoryRepository->getAll();
        return ApiResponse::success($expenseCategories, 'Expense categories retrieved successfully');
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $expenseCategory = $this->expenseCategoryRepository->create($request->validated());
        return ApiResponse::success($expenseCategory, 'Expense category created successfully');
    }

    public function show(int $id): JsonResponse
    {
        $expenseCategory = $this->expenseCategoryRepository->findById($id);

        if (!$expenseCategory) {
            return ApiResponse::error('Expense category not found', null, 404);
        }

        return ApiResponse::success($expenseCategory, 'Expense category retrieved successfully');
    }

    public function update(UpdateExpenseCategoryRequest $request, int $id): JsonResponse
    {
        $expenseCategory = $this->expenseCategoryRepository->findById($id);

        if (!$expenseCategory) {
            return ApiResponse::error('Expense category not found', null, 404);
        }

        $this->expenseCategoryRepository->update($expenseCategory, $request->validated());
        $expenseCategory->refresh();

        return ApiResponse::success($expenseCategory, 'Expense category updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $expenseCategory = $this->expenseCategoryRepository->findById($id);

        if (!$expenseCategory) {
            return ApiResponse::error('Expense category not found', null, 404);
        }

        $this->expenseCategoryRepository->delete($expenseCategory);
        return ApiResponse::success('Expense category deleted successfully');
    }
}

