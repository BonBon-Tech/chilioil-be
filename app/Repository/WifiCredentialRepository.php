<?php

namespace App\Repository;

use App\Models\WifiCredential;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class WifiCredentialRepository
{
    public function all(): Collection
    {
        return WifiCredential::with([])->orderBy('is_active', 'desc')->get(); // Add relations in with([]) if any
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return WifiCredential::with([])->orderBy('is_active', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?WifiCredential
    {
        return WifiCredential::with([])->find($id);
    }

    public function create(array $data): WifiCredential
    {
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
