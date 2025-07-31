<?php

namespace App\Repository;

use App\Models\Product;
use Illuminate\Http\UploadedFile;

class ProductRepository
{
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with(['store', 'productCategory'])->get();
    }

    public function paginate($perPage = 15, array $filters = [])
    {
        $query = Product::with(['store', 'productCategory']);

        // Search by name
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Filter by name
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        // Filter by code
        if (isset($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        // Filter by store_id
        if (isset($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        // Filter by product_category_id
        if (isset($filters['product_category_id'])) {
            $query->where('product_category_id', $filters['product_category_id']);
        }

        // Filter by selling_type
        if (isset($filters['selling_type'])) {
            $query->where('selling_type', $filters['selling_type']);
        }

        // Filter by price range
        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?Product
    {
        return Product::with(['store', 'productCategory'])->find($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): ?Product
    {
        $product = Product::find($id);
        if (!$product) {
            return null;
        }
        $product->update($data);
        return $product->fresh(['store', 'productCategory']);
    }

    public function delete(int $id): bool
    {
        return Product::destroy($id) > 0;
    }
}
