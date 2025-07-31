<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Repository\TransactionRepository;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionRepository $transactions
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $transactions = $this->transactions->paginate($perPage, $request->all());
        return ApiResponse::success($transactions, 'Transaction list fetched successfully');
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
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

