<?php

namespace App\Repository;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;

class ProductCategoryRepository
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

        return $query->paginate($perPage);
    }

    public function find(int $id): ?ProductCategory
    {
        return $this->scopedQuery()->find($id);
    }

    public function create(array $data): ProductCategory
    {
        $data['company_id'] = $data['company_id'] ?? Auth::user()->company_id;
        return ProductCategory::create($data);
    }

    public function update(int $id, array $data): ?ProductCategory
    {
        $category = $this->scopedQuery()->find($id);
        if (!$category) {
            return null;
        }
        $category->update($data);
        return $category;
    }

    public function delete(int $id): bool
    {
        $category = $this->scopedQuery()->find($id);
        if (!$category) {
            return false;
        }
        return $category->delete();
    }
}
