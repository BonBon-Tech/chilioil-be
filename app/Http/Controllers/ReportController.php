<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Repository\ReportRepository;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(protected ReportRepository $repo) {}

    private function defaultDates(Request $request): array
    {
        $end   = $request->query('end_date',   Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        $start = $request->query('start_date', Carbon::now('Asia/Jakarta')->subDays(29)->format('Y-m-d'));
        return [$start, $end];
    }

    // 1. Sales Summary
    public function salesSummary(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $period  = $request->query('period', 'daily'); // daily|weekly|monthly
        $storeId = $request->query('store_id');

        $data = $this->repo->getSalesSummary($period, $start, $end, $storeId);
        return ApiResponse::success($data, 'Sales summary');
    }

    // 2. Sales by Type
    public function salesByType(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $storeId = $request->query('store_id');

        $data = $this->repo->getSalesByType($start, $end, $storeId);
        return ApiResponse::success($data, 'Sales by type');
    }

    // 3. Sales by Payment
    public function salesByPayment(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $storeId = $request->query('store_id');

        $data = $this->repo->getSalesByPayment($start, $end, $storeId);
        return ApiResponse::success($data, 'Sales by payment type');
    }

    // 4. Sales by Store
    public function salesByStore(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);

        $data = $this->repo->getSalesByStore($start, $end);
        return ApiResponse::success($data, 'Sales by store');
    }

    // 5. Top Products
    public function topProducts(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $storeId = $request->query('store_id');
        $limit   = (int) $request->query('limit', 10);

        $data = $this->repo->getTopProducts($start, $end, $storeId, $limit);
        return ApiResponse::success($data, 'Top products');
    }

    // 6. Expense Summary
    public function expenseSummary(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $period  = $request->query('period', 'monthly');
        $storeId = $request->query('store_id');

        $data = $this->repo->getExpenseSummary($period, $start, $end, $storeId);
        return ApiResponse::success($data, 'Expense summary');
    }

    // 7. Expense by Category
    public function expenseByCategory(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $storeId = $request->query('store_id');

        $data = $this->repo->getExpenseByCategory($start, $end, $storeId);
        return ApiResponse::success($data, 'Expense by category');
    }

    // 8. Profit & Loss
    public function profitLoss(Request $request): JsonResponse
    {
        [$start, $end] = $this->defaultDates($request);
        $storeId = $request->query('store_id');

        $data = $this->repo->getProfitLoss($start, $end, $storeId);
        return ApiResponse::success($data, 'Profit and loss');
    }
}
