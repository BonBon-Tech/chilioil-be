<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Repository\ExpenseRepository;
use App\Traits\CheckDemoLimit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $filters = $request->all();
        if ($this->isStaff($request)) {
            $this->ensureStaffStore($request, $request->query('store_id'));
            $filters['store_id'] = $request->user()->store_id;
        }
        $expenses = $this->expenseRepository->paginate($perPage, $filters);

        return ApiResponse::success($expenses, 'Expenses retrieved successfully');
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $demoCheck = $this->checkDemoLimit(Expense::class, 10);
        if ($demoCheck) {
            return $demoCheck;
        }

        $data = $request->validated();
        $this->ensureStaffMutation($request, $data['store_id'], $data['date']);
        $expense = $this->expenseRepository->create($data);

        return ApiResponse::success($expense, 'Expense created successfully', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (! $expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }
        $this->ensureStaffStore($request, $expense->store_id);

        return ApiResponse::success($expense, 'Expense retrieved successfully');
    }

    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (! $expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }

        $data = $request->validated();
        $this->ensureStaffStore($request, $expense->store_id);
        $this->ensureStaffMutation($request, $data['store_id'], $data['date']);
        $this->expenseRepository->update($expense, $data);
        $expense->refresh()->load('expenseCategory');

        return ApiResponse::success($expense, 'Expense updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $expense = $this->expenseRepository->findById($id);

        if (! $expense) {
            return ApiResponse::error('Expense not found', null, 404);
        }
        $this->ensureStaffMutation($request, $expense->store_id, (string) $expense->date);

        $this->expenseRepository->delete($expense);

        return ApiResponse::success(null, 'Expense deleted successfully');
    }

    private function isStaff(Request $request): bool
    {
        return strtolower($request->user()->role?->name ?? '') === 'staff';
    }

    private function ensureStaffStore(Request $request, ?string $storeId): void
    {
        if (! $this->isStaff($request)) {
            return;
        }
        if ($storeId !== null && $storeId !== $request->user()->store_id) {
            throw ValidationException::withMessages([
                'store_id' => 'Staff hanya dapat mengakses toko penugasannya.',
            ]);
        }
    }

    private function ensureStaffMutation(Request $request, string $storeId, string $date): void
    {
        $this->ensureStaffStore($request, $storeId);
        if ($this->isStaff($request) && ! Carbon::parse($date)->isToday()) {
            abort(403, 'Staff hanya dapat mengubah pengeluaran hari ini.');
        }
    }
}
