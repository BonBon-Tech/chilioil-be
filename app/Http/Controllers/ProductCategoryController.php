<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use App\Repository\ProductCategoryRepository;
use App\Helpers\ApiResponse;
use App\Traits\CheckDemoLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCategoryController extends Controller
{
    use CheckDemoLimit;
    protected ProductCategoryRepository $categories;

    public function __construct(ProductCategoryRepository $categories)
    {
        $this->categories = $categories;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $categories = $this->categories->paginate($perPage, $request->all());
        return ApiResponse::success($categories, 'Product category list fetched successfully');
    }

    public function show(string $id): JsonResponse
    {
        $category = $this->categories->find($id);
        if (!$category) {
            return ApiResponse::error('Product category not found', null, 404);
        }
        return ApiResponse::success($category, 'Product category detail fetched successfully');
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $demoCheck = $this->checkDemoLimit(ProductCategory::class, 2);
        if ($demoCheck) return $demoCheck;

        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        $category = $this->categories->create($data);
        return ApiResponse::success($category, 'Product category created successfully');
    }

    public function update(UpdateProductCategoryRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        // Auto-generate slug if name updated but slug not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $id);
        }

        $category = $this->categories->update($id, $data);
        if (!$category) {
            return ApiResponse::error('Product category not found', null, 404);
        }
        return ApiResponse::success($category, 'Product category updated successfully');
    }

    private function generateUniqueSlug(string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (
            ProductCategory::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->categories->delete($id);
        if (!$deleted) {
            return ApiResponse::error('Product category not found', null, 404);
        }
        return ApiResponse::success(null, 'Product category deleted successfully');
    }
}
