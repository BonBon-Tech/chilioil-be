<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Repository\StockOpnameRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockOpnameController extends Controller
{
    public function __construct(
        protected StockOpnameRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $data = $this->repository->getAll($request->all(), $perPage);
        return ApiResponse::success($data, 'Stock opname list');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opname_date'         => 'required|date',
            'store_id' => ['nullable', 'string', Rule::exists('stores', 'id')->where('company_id', $request->get('company_id'))],
            'product_category_id' => ['nullable', 'string', Rule::exists('product_categories', 'id')->where('company_id', $request->get('company_id'))],
            'notes'               => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        if ($user?->role?->name === 'staff') {
            if (!$user->store_id) {
                return ApiResponse::error('Forbidden', null, 403);
            }

            if (!empty($validated['store_id']) && $validated['store_id'] !== $user->store_id) {
                throw ValidationException::withMessages([
                    'store_id' => ['Staff hanya dapat membuat stock opname untuk toko yang ditugaskan.'],
                ]);
            }

            $validated['store_id'] = $user->store_id;
        }

        $validated['started_by'] = $user->id;

        try {
            $opname = $this->repository->create($validated);
        } catch (\DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), null, 422);
        }

        return ApiResponse::success($opname, 'Stock opname berhasil dibuat', 201);
    }

    public function show(string $id): JsonResponse
    {
        $opname = $this->repository->find($id);
        if (!$opname) {
            return ApiResponse::error('Stock opname tidak ditemukan', null, 404);
        }
        return ApiResponse::success($opname, 'Stock opname detail');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $opname = $this->repository->update($id, $validated);
        if (!$opname) {
            return ApiResponse::error('Stock opname tidak ditemukan atau tidak dalam status pending', null, 422);
        }

        return ApiResponse::success($opname, 'Stock opname diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->repository->delete($id);
        if (!$deleted) {
            return ApiResponse::error('Stock opname tidak ditemukan atau tidak dalam status dibatalkan', null, 422);
        }
        return ApiResponse::success(null, 'Stock opname dihapus');
    }

    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $validated = $request->validate([
            'counted_stock' => 'required|numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ]);

        $item = $this->repository->updateItem($id, $itemId, $validated, Auth::id());
        if (!$item) {
            return ApiResponse::error('Item tidak ditemukan atau opname tidak dalam status pending', null, 422);
        }

        return ApiResponse::success($item, 'Item diperbarui');
    }

    public function approve(string $id): JsonResponse
    {
        $opname = $this->repository->approve($id, Auth::id());
        if (!$opname) {
            return ApiResponse::error('Stock opname tidak ditemukan atau tidak dalam status pending', null, 422);
        }

        return ApiResponse::success($opname, 'Stock opname berhasil disetujui');
    }

    public function reject(string $id): JsonResponse
    {
        $opname = $this->repository->reject($id);
        if (!$opname) {
            return ApiResponse::error('Stock opname tidak ditemukan atau tidak dalam status pending', null, 422);
        }

        return ApiResponse::success($opname, 'Stock opname ditolak');
    }

    public function cancel(string $id): JsonResponse
    {
        $opname = $this->repository->cancel($id);
        if (!$opname) {
            return ApiResponse::error('Stock opname tidak ditemukan atau tidak dalam status pending', null, 422);
        }

        return ApiResponse::success($opname, 'Stock opname dibatalkan');
    }
}
