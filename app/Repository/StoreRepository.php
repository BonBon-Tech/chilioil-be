<?php

namespace App\Repository;

use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class StoreRepository
{
    private function getCompanyId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->role && $user->role->name === 'owner') {
            return null;
        }
        return $user?->company_id;
    }

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

    public function find(int $id): ?Store
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): Store
    {
        $data['company_id'] = $data['company_id'] ?? Auth::user()->company_id;
        return Store::create($data);
    }

    public function update(int $id, array $data): ?Store
    {
        $store = $this->scopedQuery()->find($id);
        if (!$store) {
            return null;
        }
        $store->update($data);
        return $store;
    }

    public function delete(int $id): bool
    {
        $store = $this->scopedQuery()->find($id);
        if (!$store) {
            return false;
        }
        return $store->delete();
    }
}
