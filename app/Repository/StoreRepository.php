<?php

namespace App\Repository;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreRepository
{
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Store::all();
    }

    public function find(int $id): ?Store
    {
        return Store::find($id);
    }

    public function create(array $data): Store
    {
        return Store::create($data);
    }

    public function update(int $id, array $data): ?Store
    {
        $store = Store::find($id);
        if (!$store) {
            return null;
        }
        $store->update($data);
        return $store;
    }

    public function delete(int $id): bool
    {
        return Store::destroy($id) > 0;
    }
}

