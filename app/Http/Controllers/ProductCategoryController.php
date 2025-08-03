<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Repository\ProductCategoryRepository;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
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

    public function show(int $id): JsonResponse
    {
        $category = $this->categories->find($id);
        if (!$category) {
            return ApiResponse::error('Product category not found', null, 404);
        }
        return ApiResponse::success($category, 'Product category detail fetched successfully');
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());
        return ApiResponse::success($category, 'Product category created successfully');
    }

    public function update(UpdateProductCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categories->update($id, $request->validated());
        if (!$category) {
            return ApiResponse::error('Product category not found', null, 404);
        }
        return ApiResponse::success($category, 'Product category updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->categories->delete($id);
        if (!$deleted) {
            return ApiResponse::error('Product category not found', null, 404);
        }
        return ApiResponse::success(null, 'Product category deleted successfully');
    }
}
