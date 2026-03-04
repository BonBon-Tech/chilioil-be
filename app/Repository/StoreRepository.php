<?php

namespace App\Repository;

use App\Helpers\JwtClaims;
use App\Models\Store;
use App\Traits\UsesCompanyScope;

class StoreRepository
{
    use UsesCompanyScope;

    private function scopedQuery()
    {
        $query = Store::query();
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->scopedQuery()->get();
    }

    public function find(string $id): ?Store
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): Store
    {
        $data['company_id'] = $data['company_id'] ?? JwtClaims::companyId();
        return Store::create($data);
    }

    public function update(string $id, array $data): ?Store
    {
        $store = $this->scopedQuery()->find($id);
        if (!$store) {
            return null;
        }
        $store->update($data);
        return $store;
    }

    public function delete(string $id): bool
    {
        $store = $this->scopedQuery()->find($id);
        if (!$store) {
            return false;
        }
        return $store->delete();
    }
}
