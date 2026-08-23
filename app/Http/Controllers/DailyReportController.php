<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreDailyReportCreditorRequest;
use App\Http\Requests\StoreDailyReportDebtRequest;
use App\Http\Requests\StoreDailyReportIncomeRequest;
use App\Http\Requests\StoreDailyReportRestockRequest;
use App\Http\Requests\UpdateDailyReportCreditorRequest;
use App\Repository\DailyReportRepository;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DailyReportController extends Controller
{
    public function __construct(private DailyReportRepository $reports) {}

    public function storeCreditor(StoreDailyReportCreditorRequest $request): JsonResponse
    {
        $creditor = $this->reports->createCreditor(
            $request->user()->company_id,
            $request->validated(),
        );

        return ApiResponse::success($creditor, 'Penanggung berhasil ditambahkan', 201);
    }

    public function creditors(Request $request): JsonResponse
    {
        $storeId = $this->validatedReadStore($request);

        return ApiResponse::success(
            $this->reports->creditors($request->user()->company_id, $storeId),
            'Daftar penanggung berhasil dimuat',
        );
    }

    public function updateCreditor(UpdateDailyReportCreditorRequest $request, string $creditor): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            $this->reports->updateCreditor(
                $request->user()->company_id,
                $data['store_id'],
                $creditor,
                $data,
            ),
            'Penanggung berhasil diperbarui',
        );
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'tab' => 'required|in:restock,debt,income',
            'per_page' => 'nullable|integer|min:1|max:15',
        ]);
        $storeId = $this->validatedReadStore($request);

        return ApiResponse::success(
            $this->reports->history(
                $request->user()->company_id,
                $storeId,
                (int) $request->query('per_page', 15),
            ),
            'Riwayat laporan berhasil dimuat',
        );
    }

    public function updateRestock(StoreDailyReportRestockRequest $request, string $date): JsonResponse
    {
        $this->ensureWriteAllowed($request, $date);
        $data = $request->validated();

        return ApiResponse::success(
            $this->reports->updateRestock(
                $request->user()->company_id,
                $data['store_id'],
                $date,
                $data['creditor_id'],
                $data['items'],
            ),
            'Restok berhasil disimpan',
        );
    }

    public function updateDebt(StoreDailyReportDebtRequest $request, string $date): JsonResponse
    {
        $this->ensureWriteAllowed($request, $date);
        $data = $request->validated();

        return ApiResponse::success(
            $this->reports->updateDebt(
                $request->user()->company_id,
                $data['store_id'],
                $date,
                $data['payments'],
            ),
            'Pembayaran utang berhasil disimpan',
        );
    }

    public function updateIncome(StoreDailyReportIncomeRequest $request, string $date): JsonResponse
    {
        $this->ensureWriteAllowed($request, $date);
        $data = $request->validated();

        return ApiResponse::success(
            $this->reports->updateIncome(
                $request->user()->company_id,
                $data['store_id'],
                $date,
                $data['amounts'],
            ),
            'Pendapatan berhasil disimpan',
        );
    }

    public function show(Request $request, string $date): JsonResponse
    {
        $storeId = $this->validatedReadStore($request);

        return ApiResponse::success(
            $this->reports->detail($request->user()->company_id, $storeId, $date),
            'Laporan harian berhasil dimuat',
        );
    }

    private function ensureWriteAllowed(Request $request, string $date): void
    {
        $parsedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        if ($parsedDate->isFuture()) {
            throw ValidationException::withMessages(['date' => 'Tanggal laporan tidak boleh di masa depan.']);
        }

        $role = strtolower($request->user()->role?->name ?? '');
        if (! in_array($role, ['admin', 'staff'], true)) {
            abort(403, 'Forbidden');
        }
        if ($role === 'staff') {
            if ($request->user()->store_id !== $request->input('store_id')) {
                throw ValidationException::withMessages(['store_id' => 'Staff hanya dapat mengakses toko penugasannya.']);
            }
            if ($parsedDate->toDateString() !== today()->toDateString()) {
                abort(403, 'Staff hanya dapat mengubah laporan hari ini.');
            }
        }
    }

    private function validatedReadStore(Request $request): string
    {
        $storeId = (string) $request->query('store_id');
        $request->validate(['store_id' => 'required|uuid']);

        $exists = $request->user()->company->stores()->whereKey($storeId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['store_id' => 'Toko tidak valid.']);
        }
        if (strtolower($request->user()->role?->name ?? '') === 'staff' && $request->user()->store_id !== $storeId) {
            throw ValidationException::withMessages(['store_id' => 'Staff hanya dapat mengakses toko penugasannya.']);
        }

        return $storeId;
    }
}
