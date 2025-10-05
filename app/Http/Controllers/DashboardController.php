<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Repository\DashboardRepository;
use App\Http\Resources\ProductSalesResource;

class DashboardController extends Controller
{
    protected DashboardRepository $dashboardRepository;

    public function __construct(DashboardRepository $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    public function summary(): JsonResponse
    {
        $data = $this->dashboardRepository->getSummary();
        return ApiResponse::success($data, 'Dashboard summary');
    }

    public function productSales(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        $sales = $this->dashboardRepository->getProductSales($storeId);
        $data = ProductSalesResource::collection($sales);
        return ApiResponse::success($data, 'Product sales summary');
    }

    public function storeSales(Request $request): JsonResponse
    {
        $sales = $this->dashboardRepository->getStoreSales();
        return ApiResponse::success($sales, 'Store sales summary');
    }

    public function storeOnlineSales(Request $request): JsonResponse
    {
        $sales = $this->dashboardRepository->getOnlineStoreSales();
        return ApiResponse::success($sales, 'Store sales summary');
    }

    public function storeDailyOnlineSales(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'total' => $this->dashboardRepository->getDailyOnlineSales(),
        ], 'Store sales summary');
    }

    public function storeDailyOfflineSales(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'total' => $this->dashboardRepository->getDailyOfflineSales(),
        ], 'Store sales summary');
    }
}
