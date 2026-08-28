<?php

namespace App\Repository;

use App\Models\Product;
use App\Models\Company;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Traits\UsesCompanyScope;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameRepository
{
    use UsesCompanyScope;

    private function scopedQuery()
    {
        $query = StockOpname::with([
            'store:id,name',
            'startedBy:id,name',
            'approvedBy:id,name',
        ]);

        if (!$this->hasConsistentTenantIdentity()) {
            return $query->whereRaw('1 = 0');
        }

        $companyId = $this->getCompanyId();
        if (!$companyId) {
            return $query->whereRaw('1 = 0');
        }
        $query->forCompany($companyId);

        if ($this->isStaff()) {
            $query->where('store_id', $this->getStoreId());
        }

        return $query;
    }

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->scopedQuery();

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('opname_date', $filters['date']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('opname_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('opname_date', '<=', $filters['end_date']);
        }

        return $query->orderByDesc('opname_date')
            ->orderByDesc('sequence_number')
            ->paginate($perPage);
    }

    public function find(string $id): ?StockOpname
    {
        return $this->scopedQuery()
            ->with('items.countedBy:id,name')
            ->find($id);
    }

    /**
     * Check if a pending opname exists that would block creation.
     * Returns an error message string if blocked, null if allowed.
     */
    public function checkPendingConflict(?string $storeId): ?string
    {
        $companyId = $this->getCompanyId();

        // All-store pending opname always blocks
        $allStorePending = StockOpname::where('company_id', $companyId)
            ->whereNull('store_id')
            ->where('status', 'pending')
            ->exists();

        if ($allStorePending) {
            return 'Ada opname semua toko yang masih pending. Selesaikan atau batalkan terlebih dahulu.';
        }

        if ($storeId === null) {
            // New all-store opname — block if any store has a pending opname
            $anyPending = StockOpname::where('company_id', $companyId)
                ->where('status', 'pending')
                ->exists();
            if ($anyPending) {
                return 'Ada opname per toko yang masih pending. Selesaikan atau batalkan terlebih dahulu sebelum membuat opname semua toko.';
            }
        } else {
            // New per-store opname — block if that store has a pending opname
            $storePending = StockOpname::where('company_id', $companyId)
                ->where('store_id', $storeId)
                ->where('status', 'pending')
                ->exists();
            if ($storePending) {
                return 'Toko ini sudah memiliki opname yang masih pending. Selesaikan atau batalkan terlebih dahulu.';
            }
        }

        return null;
    }

    public function create(array $data): StockOpname
    {
        abort_unless($this->hasConsistentTenantIdentity() && $this->getCompanyId(), 403, 'Forbidden');

        return DB::transaction(function () use ($data) {
            $companyId = $this->getCompanyId();
            $company = Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($data['opname_date']);
            $storeId = $data['store_id'] ?? null;
            $categoryId = $data['product_category_id'] ?? null;

            if ($conflict = $this->checkPendingConflict($storeId)) {
                throw new \DomainException($conflict);
            }

            $productQuery = Product::where('selling_type', 'Purchase')
                ->whereHas('store', fn($q) => $q->where('company_id', $companyId));

            if ($storeId) {
                $productQuery->where('store_id', $storeId);
            }
            if ($categoryId) {
                $productQuery->where('product_category_id', $categoryId);
            }

            $products = $productQuery->get(['id', 'name']);
            if ($products->isEmpty()) {
                throw new \DomainException('Tidak ada produk pembelian yang ditemukan untuk filter yang dipilih.');
            }

            $lastStocks = StockOpnameItem::query()
                ->select('stock_opname_items.product_id', 'stock_opname_items.counted_stock')
                ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
                ->where('stock_opnames.company_id', $companyId)
                ->where('stock_opnames.status', 'approved')
                ->whereIn('stock_opname_items.product_id', $products->pluck('id'))
                ->orderByDesc('stock_opnames.approved_at')
                ->orderByDesc('stock_opname_items.created_at')
                ->get()
                ->unique('product_id')
                ->keyBy('product_id');

            $seq = (int) StockOpname::withTrashed()
                ->where('company_id', $companyId)
                ->whereDate('opname_date', $date)
                ->max('sequence_number') + 1;

            $opname = StockOpname::create([
                'code' => 'SO-' . strtoupper($company->slug) . '-' . $date->format('Ymd') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT),
                'company_id' => $companyId,
                'store_id' => $storeId,
                'started_by' => $data['started_by'],
                'opname_date' => $date->toDateString(),
                'sequence_number' => $seq,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            $opname->items()->createMany($products->map(fn($product) => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'uom' => null,
                'last_known_stock' => (float) ($lastStocks->get($product->id)?->counted_stock ?? 0),
                'counted_stock' => null,
                'variance' => null,
            ])->all());

            return $opname->load(['store:id,name', 'startedBy:id,name', 'items']);
        });
    }

    public function update(string $id, array $data): ?StockOpname
    {
        return DB::transaction(function () use ($id, $data) {
            $opname = $this->scopedQuery()->lockForUpdate()->find($id);
            if (!$opname || $opname->status !== 'pending') {
                return null;
            }

            $opname->update(['notes' => $data['notes'] ?? $opname->notes]);

            return $opname->fresh(['store:id,name', 'startedBy:id,name']);
        });
    }

    public function updateItem(string $opnameId, string $itemId, array $data, string $userId): ?StockOpnameItem
    {
        return DB::transaction(function () use ($opnameId, $itemId, $data, $userId) {
            $opname = $this->scopedQuery()->lockForUpdate()->find($opnameId);
            if (!$opname || $opname->status !== 'pending') {
                return null;
            }

            $item = $opname->items()->lockForUpdate()->find($itemId);
            if (!$item) {
                return null;
            }

            $countedStock = (float) $data['counted_stock'];
            $variance = $countedStock - (float) $item->last_known_stock;

            $item->update([
                'counted_stock' => $countedStock,
                'variance'      => $variance,
                'counted_by'    => $userId,
                'counted_at'    => now(),
                'notes'         => $data['notes'] ?? $item->notes,
            ]);

            return $item->fresh('countedBy:id,name');
        });
    }

    public function approve(string $id, string $adminUserId): ?StockOpname
    {
        return DB::transaction(function () use ($id, $adminUserId) {
            $opname = $this->scopedQuery()->lockForUpdate()->find($id);
            if (!$opname || $opname->status !== 'pending') {
                return null;
            }

            if ($opname->items()->whereNull('counted_stock')->exists()) {
                throw ValidationException::withMessages([
                    'items' => ['Semua item harus dihitung sebelum stock opname disetujui.'],
                ]);
            }

            $opname->update([
                'status'      => 'approved',
                'approved_by' => $adminUserId,
                'approved_at' => now(),
            ]);

            return $opname->fresh(['store:id,name', 'startedBy:id,name', 'approvedBy:id,name']);
        });
    }

    public function reject(string $id): ?StockOpname
    {
        return DB::transaction(function () use ($id) {
            $opname = $this->scopedQuery()->lockForUpdate()->find($id);
            if (!$opname || $opname->status !== 'pending') {
                return null;
            }

            $opname->update(['status' => 'rejected']);

            return $opname->fresh(['store:id,name', 'startedBy:id,name']);
        });
    }

    public function cancel(string $id): ?StockOpname
    {
        return DB::transaction(function () use ($id) {
            $opname = $this->scopedQuery()->lockForUpdate()->find($id);
            if (!$opname || $opname->status !== 'pending') {
                return null;
            }

            $opname->update(['status' => 'cancelled']);

            return $opname->fresh(['store:id,name', 'startedBy:id,name']);
        });
    }

    public function delete(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $opname = $this->scopedQuery()->lockForUpdate()->find($id);
            if (!$opname || $opname->status !== 'cancelled') {
                return false;
            }

            return (bool) $opname->delete();
        });
    }
}
