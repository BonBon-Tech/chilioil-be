<?php

namespace App\Repository;

use App\Helpers\JwtClaims;
use App\Models\WifiCredential;
use App\Traits\UsesCompanyScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class WifiCredentialRepository
{
    use UsesCompanyScope;

    private function scopedQuery()
    {
        $query = WifiCredential::query();
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function all(): Collection
    {
        return $this->scopedQuery()->orderBy('is_active', 'desc')->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->scopedQuery()->orderBy('is_active', 'desc')->paginate($perPage);
    }

    public function find(string $id): ?WifiCredential
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): WifiCredential
    {
        $data['company_id'] = $data['company_id'] ?? JwtClaims::companyId();
        return WifiCredential::create($data);
    }

    public function update(WifiCredential $wifiCredential, array $data): WifiCredential
    {
        $wifiCredential->update($data);
        return $wifiCredential;
    }

    public function delete(WifiCredential $wifiCredential): bool
    {
        return $wifiCredential->delete();
    }
}
