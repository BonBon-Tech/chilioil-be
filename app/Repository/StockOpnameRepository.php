<?php

namespace App\Repository;

use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Traits\UsesCompanyScope;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->forCompany($companyId);
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
        $companyId = $this->getCompanyId();
        $date      = Carbon::parse($data['opname_date']);
        $storeId   = $data['store_id'] ?? null;
        $categoryId = $data['product_category_id'] ?? null;

        $seq  = StockOpname::where('company_id', $companyId)
            ->whereDate('opname_date', $date)
            ->count() + 1;

        $code = 'SO-' . $date->format('Ymd') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

        $opname = StockOpname::create([
            'code'            => $code,
            'company_id'      => $companyId,
            'store_id'        => $storeId,
            'started_by'      => $data['started_by'],
            'opname_date'     => $date->toDateString(),
            'sequence_number' => $seq,
            'status'          => 'pending',
            'notes'           => $data['notes'] ?? null,
        ]);

        $productQuery = Product::where('selling_type', 'Purchase')
            ->whereHas('store', fn($q) => $q->where('company_id', $companyId));

        if ($storeId) {
            $productQuery->where('store_id', $storeId);
        }

        if ($categoryId) {
            $productQuery->where('product_category_id', $categoryId);
        }

        $products = $productQuery->get();

        foreach ($products as $product) {
            $lastItem = StockOpnameItem::whereHas('stockOpname', function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->where('status', 'approved');
            })
                ->where('product_id', $product->id)
                ->orderByDesc('created_at')
                ->first();

            $opname->items()->create([
                'product_id'       => $product->id,
                'product_name'     => $product->name,
                'uom'              => null,
                'last_known_stock' => (float) ($lastItem?->counted_stock ?? 0),
                'counted_stock'    => null,
                'variance'         => null,
            ]);
        }

        return $opname->load(['store:id,name', 'startedBy:id,name', 'items']);
    }

    public function update(string $id, array $data): ?StockOpname
    {
        $opname = $this->scopedQuery()->find($id);
        if (!$opname || $opname->status !== 'pending') {
            return null;
        }

        $opname->update(['notes' => $data['notes'] ?? $opname->notes]);
        return $opname->fresh(['store:id,name', 'startedBy:id,name']);
    }

    public function updateItem(string $opnameId, string $itemId, array $data, string $userId): ?StockOpnameItem
    {
        $opname = $this->scopedQuery()->find($opnameId);
        if (!$opname || $opname->status !== 'pending') {
            return null;
        }

        $item = $opname->items()->find($itemId);
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
    }

    public function approve(string $id, string $adminUserId): ?StockOpname
    {
        $opname = $this->scopedQuery()->find($id);
        if (!$opname || $opname->status !== 'pending') {
            return null;
        }

        $opname->update([
            'status'      => 'approved',
            'approved_by' => $adminUserId,
            'approved_at' => now(),
        ]);

        return $opname->fresh(['store:id,name', 'startedBy:id,name', 'approvedBy:id,name']);
    }

    public function reject(string $id): ?StockOpname
    {
        $opname = $this->scopedQuery()->find($id);
        if (!$opname || $opname->status !== 'pending') {
            return null;
        }

        $opname->update(['status' => 'rejected']);
        return $opname->fresh(['store:id,name', 'startedBy:id,name']);
    }

    public function cancel(string $id): ?StockOpname
    {
        $opname = $this->scopedQuery()->find($id);
        if (!$opname || $opname->status !== 'pending') {
            return null;
        }

        $opname->update(['status' => 'cancelled']);
        return $opname->fresh(['store:id,name', 'startedBy:id,name']);
    }

    public function delete(string $id): bool
    {
        $opname = $this->scopedQuery()->find($id);
        if (!$opname || $opname->status !== 'cancelled') {
            return false;
        }
        return (bool) $opname->delete();
    }
}
