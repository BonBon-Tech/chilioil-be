<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Repository\TransactionRepository;
use App\Helpers\ApiResponse;
use App\Models\Transaction;
use App\Traits\CheckDemoLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    use CheckDemoLimit;
    public function __construct(
        private TransactionRepository $transactions
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $filters = $request->all();

        // Basic plan: only show offline transactions
        $user = Auth::user();
        if ($user->company && $user->company->isBasic()) {
            $filters['type'] = 'OFFLINE';
        }

        $transactions = $this->transactions->paginate($perPage, $filters);
        return ApiResponse::success($transactions, 'Transaction list fetched successfully');
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $demoCheck = $this->checkDemoLimit(Transaction::class, 10);
        if ($demoCheck) return $demoCheck;

        // Basic plan: only allow offline transactions
        $user = Auth::user();
        if ($user->company && $user->company->isBasic()) {
            $validated = $request->validated();
            if (isset($validated['type']) && $validated['type'] !== 'OFFLINE') {
                return ApiResponse::error('Fitur penjualan online hanya tersedia di paket Pro', null, 403);
            }
        }

        $transaction = $this->transactions->create($request->validated());
        return ApiResponse::success($transaction, 'Transaction created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $transaction = $this->transactions->findById($id);

        if (!$transaction) {
            return ApiResponse::error('Transaction not found', 404);
        }

        return ApiResponse::success($transaction, 'Transaction fetched successfully');
    }

    public function update(UpdateTransactionRequest $request, int $id): JsonResponse
    {
        $transaction = $this->transactions->update($id, $request->validated());

        if (!$transaction) {
            return ApiResponse::error('Transaction not found', 404);
        }

        return ApiResponse::success($transaction, 'Transaction updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->transactions->delete($id);

        if (!$deleted) {
            return ApiResponse::error('Transaction not found', 404);
        }

        return ApiResponse::success(null, 'Transaction deleted successfully');
    }
}

