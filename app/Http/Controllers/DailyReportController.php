<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreCashAccountSetupRequest;
use App\Http\Requests\StoreCashAdjustmentRequest;
use App\Http\Requests\StoreCashTransferRequest;
use App\Http\Requests\StoreDailyReportCreditorRequest;
use App\Http\Requests\StoreDailyReportDebtRequest;
use App\Http\Requests\StoreDailyReportExpenseRequest;
use App\Http\Requests\StoreDailyReportIncomeRequest;
use App\Http\Requests\StoreDailyReportRestockRequest;
use App\Http\Requests\UpdateDailyReportCreditorRequest;
use App\Repository\CashLedgerRepository;
use App\Repository\DailyReportRepository;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DailyReportController extends Controller
{
    public function __construct(private DailyReportRepository $reports, private CashLedgerRepository $cash) {}

    public function accounts(Request $request): JsonResponse
    {
        $storeId = $this->validatedReadStore($request);

        return ApiResponse::success($this->cash->overview($request->user()->company_id, $storeId), 'Daftar akun berhasil dimuat');
    }

    public function setupAccounts(StoreCashAccountSetupRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            $this->cash->setup($request->user()->company_id, $data['store_id'], $data['opening_date'], $data['balances'], $request->user()->id),
            'Saldo awal berhasil disimpan',
        );
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => 'required|uuid',
            'name' => 'required|string|max:100',
            'opening_date' => 'required|date|before_or_equal:today',
            'opening_balance' => 'required|numeric|min:0',
        ]);
        $storeId = $this->validatedBodyStore($request);

        return ApiResponse::success(
            $this->cash->createAccount($request->user()->company_id, $storeId, $data, $request->user()->id),
            'Akun berhasil ditambahkan',
            201,
        );
    }

    public function updateMappings(Request $request): JsonResponse
    {
        $channels = ['shopeefood', 'grabfood', 'gofood', 'qris', 'cash'];
        $rules = ['store_id' => 'required|uuid', 'mappings' => 'required|array:'.implode(',', $channels)];
        foreach ($channels as $channel) {
            $rules['mappings.'.$channel] = 'required|uuid';
        }
        $data = $request->validate($rules);
        $storeId = $this->validatedBodyStore($request);

        return ApiResponse::success(
            $this->cash->updateMappings($request->user()->company_id, $storeId, $data['mappings']),
            'Mapping sales berhasil diperbarui',
        );
    }

    public function updateAccount(Request $request, string $account): JsonResponse
    {
        $data = $request->validate([
            'store_id' => 'required|uuid',
            'name' => 'required|string|max:100',
            'is_active' => 'required|boolean',
        ]);
        $storeId = $this->validatedBodyStore($request);

        return ApiResponse::success(
            $this->cash->updateAccount($request->user()->company_id, $storeId, $account, $data),
            'Akun berhasil diperbarui',
        );
    }

    public function transfer(StoreCashTransferRequest $request): JsonResponse
    {
        $this->ensureWriteAllowed($request, $request->validated('date'));
        $data = $request->validated();

        return ApiResponse::success(
            $this->cash->transfer($request->user()->company_id, $data['store_id'], $data, $request->user()->id),
            'Transfer berhasil disimpan',
            201,
        );
    }

    public function adjust(StoreCashAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            $this->cash->adjust($request->user()->company_id, $data['store_id'], $data, $request->user()->id),
            'Koreksi saldo berhasil disimpan',
            201,
        );
    }

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
            'tab' => 'required|in:restock,expense,debt,income,balance',
            'per_page' => 'nullable|integer|min:1|max:15',
        ]);
        $storeId = $this->validatedReadStore($request);

        $history = $request->query('tab') === 'balance'
            ? $this->cash->history($request->user()->company_id, $storeId, (int) $request->query('per_page', 15))
            : $this->reports->history($request->user()->company_id, $storeId, (int) $request->query('per_page', 15));

        return ApiResponse::success(
            $history,
            'Riwayat laporan berhasil dimuat',
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $storeId = $this->validatedReadStore($request);

        return ApiResponse::success(
            $this->reports->summary($request->user()->company_id, $storeId),
            'Summary laporan berhasil dimuat',
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
                $request->user()->id,
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
                $request->user()->id,
            ),
            'Pendapatan berhasil disimpan',
        );
    }

    public function updateExpenses(StoreDailyReportExpenseRequest $request, string $date): JsonResponse
    {
        $this->ensureWriteAllowed($request, $date);
        $data = $request->validated();

        return ApiResponse::success(
            $this->reports->updateExpenses($request->user()->company_id, $data['store_id'], $date, $data['items'], $request->user()->id),
            'Expense berhasil disimpan',
        );
    }

    public function appendExpenses(StoreDailyReportExpenseRequest $request, string $date): JsonResponse
    {
        $this->ensureWriteAllowed($request, $date);
        $data = $request->validated();

        return ApiResponse::success(
            $this->reports->appendExpenses($request->user()->company_id, $data['store_id'], $date, $data['items'], $request->user()->id),
            'Expense berhasil ditambahkan',
            201,
        );
    }

    public function updateExpenseItem(StoreDailyReportExpenseRequest $request, string $date, string $item): JsonResponse
    {
        $this->ensureWriteAllowed($request, $date);
        $data = $request->validated();
        if (count($data['items']) !== 1) {
            throw ValidationException::withMessages(['items' => 'Edit expense hanya menerima satu item.']);
        }

        return ApiResponse::success(
            $this->reports->updateExpenseItem($request->user()->company_id, $data['store_id'], $date, $item, $data['items'][0], $request->user()->id),
            'Expense berhasil diperbarui',
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

    private function validatedBodyStore(Request $request): string
    {
        $storeId = (string) $request->input('store_id');
        if (! $request->user()->company->stores()->whereKey($storeId)->exists()) {
            throw ValidationException::withMessages(['store_id' => 'Toko tidak valid.']);
        }

        return $storeId;
    }
}
