<?php

namespace App\Repository;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class ProductRepository
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
        $query = Product::with(['store', 'productCategory']);
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->whereHas('store', fn($q) => $q->where('company_id', $companyId));
        }
        return $query;
    }

    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->scopedQuery()->get();
    }

    public function paginate($perPage = 15, array $filters = [])
    {
        $query = $this->scopedQuery();

        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        if (isset($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (isset($filters['product_category_id'])) {
            $query->where('product_category_id', $filters['product_category_id']);
        }

        if (isset($filters['selling_type'])) {
            $query->where('selling_type', $filters['selling_type']);
        }

        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?Product
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): ?Product
    {
        $product = $this->scopedQuery()->find($id);
        if (!$product) {
            return null;
        }
        $product->update($data);
        return $product->fresh(['store', 'productCategory']);
    }

    public function delete(int $id): bool
    {
        $product = $this->scopedQuery()->find($id);
        if (!$product) {
            return false;
        }
        return $product->delete();
    }
}
