<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Repository\ProductRepository;
use App\Helpers\ApiResponse;
use App\Models\Product;
use App\Traits\CheckDemoLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use CheckDemoLimit;
    protected ProductRepository $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $products = $this->products->paginate($perPage, $request->all());
        return ApiResponse::success($products, 'Product list fetched successfully');
    }

    public function show(string $id): JsonResponse
    {
        $product = $this->products->find($id);
        if (!$product) {
            return ApiResponse::error('Product not found', null, 404);
        }
        return ApiResponse::success($product, 'Product detail fetched successfully');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $demoCheck = $this->checkDemoLimit(Product::class, 2);
        if ($demoCheck) return $demoCheck;

        $product = $this->products->create($request->validated());
        return ApiResponse::success($product, 'Product created successfully');
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = $this->products->update($id, $request->validated());
        if (!$product) {
            return ApiResponse::error('Product not found', null, 404);
        }
        return ApiResponse::success($product, 'Product updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->products->delete($id);
        if (!$deleted) {
            return ApiResponse::error('Product not found', null, 404);
        }
        return ApiResponse::success(null, 'Product deleted successfully');
    }
}
