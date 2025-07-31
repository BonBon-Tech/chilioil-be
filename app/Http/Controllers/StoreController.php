<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Repository\StoreRepository;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    protected StoreRepository $stores;

    public function __construct(StoreRepository $stores)
    {
        $this->stores = $stores;
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->stores->all(), 'Store list fetched successfully');
    }

    public function show(int $id): JsonResponse
    {
        $store = $this->stores->find($id);
        if (!$store) {
            return ApiResponse::error('Store not found', null, 404);
        }
        return ApiResponse::success($store, 'Store detail fetched successfully');
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        $store = $this->stores->create($request->validated());
        return ApiResponse::success($store, 'Store created successfully');
    }

    public function update(UpdateStoreRequest $request, int $id): JsonResponse
    {
        $store = $this->stores->update($id, $request->validated());
        if (!$store) {
            return ApiResponse::error('Store not found', null, 404);
        }
        return ApiResponse::success($store, 'Store updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->stores->delete($id);
        if (!$deleted) {
            return ApiResponse::error('Store not found', null, 404);
        }
        return ApiResponse::success(null, 'Store deleted successfully');
    }
}

