<?php

namespace App\Repository;

use App\Models\ProductCategory;
use Illuminate\Http\UploadedFile;

class ProductCategoryRepository
{
    public function all(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = ProductCategory::query();

        // Search by name
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Filter by name
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        // Filter by slug
        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function paginate($perPage = 15, array $filters = [])
    {
        $query = ProductCategory::query();

        // Search by name
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Filter by name
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        // Filter by slug
        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?ProductCategory
    {
        return ProductCategory::find($id);
    }

    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function update(int $id, array $data): ?ProductCategory
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return null;
        }
        $category->update($data);
        return $category;
    }

    public function delete(int $id): bool
    {
        return ProductCategory::destroy($id) > 0;
    }
}
