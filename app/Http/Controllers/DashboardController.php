<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Repository\DashboardRepository;
use App\Http\Resources\ProductSalesResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardRepository $dashboardRepository;

    public function __construct(DashboardRepository $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    public function summary(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $data = $this->dashboardRepository->getSummary($startDate, $endDate);

        // Basic plan: hide online transaction total
        $user = Auth::user();
        if ($user->company && $user->company->isBasic()) {
            $data['online_transaction_total'] = 0;
        }

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
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $sales = $this->dashboardRepository->getStoreSales($startDate, $endDate);
        return ApiResponse::success($sales, 'Store sales summary');
    }

    public function storeOnlineSales(Request $request): JsonResponse
    {
        // Basic plan: return empty
        $user = Auth::user();
        if ($user->company && $user->company->isBasic()) {
            return ApiResponse::success([], 'Store online sales summary');
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $sales = $this->dashboardRepository->getOnlineStoreSales($startDate, $endDate);
        return ApiResponse::success($sales, 'Store sales summary');
    }

    public function storeDailyOnlineSales(Request $request): JsonResponse
    {
        // Basic plan: return 0
        $user = Auth::user();
        if ($user->company && $user->company->isBasic()) {
            return ApiResponse::success(['total' => 0], 'Store sales summary');
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        return ApiResponse::success([
            'total' => $this->dashboardRepository->getDailyOnlineSales($startDate, $endDate),
        ], 'Store sales summary');
    }

    public function storeDailyOfflineSales(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        return ApiResponse::success([
            'total' => $this->dashboardRepository->getDailyOfflineSales($startDate, $endDate),
        ], 'Store sales summary');
    }

    public function weeklyTraffic(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', Carbon::now('Asia/Jakarta')->subDays(6)->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        $data = $this->dashboardRepository->getWeeklyTraffic($startDate, $endDate);
        return ApiResponse::success($data, 'Weekly traffic data');
    }

    public function topProducts(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', Carbon::now('Asia/Jakarta')->subDays(6)->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        $limit = (int) $request->query('limit', 10);
        $data = $this->dashboardRepository->getTopProducts($startDate, $endDate, $limit);
        return ApiResponse::success($data, 'Top products');
    }
}
