<?php

namespace App\Repository;

use App\Helpers\JwtClaims;
use App\Models\ProductCategory;
use App\Traits\UsesCompanyScope;

class ProductCategoryRepository
{
    use UsesCompanyScope;

    private function scopedQuery()
    {
        $query = ProductCategory::query();
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function all(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->scopedQuery();

        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['store_id'])) {
            $query->whereHas('products', fn($q) => $q->where('store_id', $filters['store_id']));
        }

        return $query->get();
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

        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['store_id'])) {
            $query->whereHas('products', fn($q) => $q->where('store_id', $filters['store_id']));
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): ?ProductCategory
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): ProductCategory
    {
        $data['company_id'] = $data['company_id'] ?? JwtClaims::companyId();
        return ProductCategory::create($data);
    }

    public function update(string $id, array $data): ?ProductCategory
    {
        $category = $this->scopedQuery()->find($id);
        if (!$category) {
            return null;
        }
        $category->update($data);
        return $category;
    }

    public function delete(string $id): bool
    {
        $category = $this->scopedQuery()->find($id);
        if (!$category) {
            return false;
        }
        return $category->delete();
    }
}
