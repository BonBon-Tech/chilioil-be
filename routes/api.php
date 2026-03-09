<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WifiCredentialController;
use App\Http\Controllers\InvitationCodeController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExportController;


// Health check — no auth required
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Exception $e) {
        $dbOk = false;
    }

    $redisOk = false;
    try {
        \Illuminate\Support\Facades\Redis::ping();
        $redisOk = true;
    } catch (\Exception $e) {
        // Redis optional
    }

    $status = $dbOk ? 'ok' : 'degraded';

    return response()->json([
        'status' => $status,
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'app' => true,
            'database' => $dbOk,
            'redis' => $redisOk,
        ],
    ], $dbOk ? 200 : 503);
});

Route::prefix('/v1')->group(function () {
    // Public auth routes (no JWT)
    Route::prefix('/auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/validate-invitation-code', [AuthController::class, 'validateInvitationCode']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/demo-credentials', [AuthController::class, 'demoCredentials']);

        Route::middleware('jwt')->group(function () {
            Route::get('/user', [AuthController::class, 'getUser']);
            Route::put('/user', [AuthController::class, 'updateUser']);
            Route::put('/company', [AuthController::class, 'updateCompany']);
            Route::put('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // Authenticated + company-scoped routes
    Route::middleware(['jwt', 'company.scope', 'check.subscription'])->group(function () {
        // User features (for mobile sidebar)
        Route::get('user/features', [FeatureController::class, 'userFeatures']);

        // Image upload
        Route::post('images', [ImageController::class, 'store']);

        // Always accessible features (basic plan)
        Route::resource('roles', RoleController::class);

        Route::get('stores', [StoreController::class, 'index']);
        Route::get('stores/{id}', [StoreController::class, 'show']);
        Route::post('stores', [StoreController::class, 'store']);
        Route::put('stores/{id}', [StoreController::class, 'update']);
        Route::delete('stores/{id}', [StoreController::class, 'destroy']);

        Route::get('product/categories', [ProductCategoryController::class, 'index']);
        Route::get('product/categories/{id}', [ProductCategoryController::class, 'show']);
        Route::post('product/categories', [ProductCategoryController::class, 'store']);
        Route::put('product/categories/{id}', [ProductCategoryController::class, 'update']);
        Route::delete('product/categories/{id}', [ProductCategoryController::class, 'destroy']);

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show']);
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::patch('products/{id}/status', [ProductController::class, 'toggleStatus']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);

        Route::apiResource('transactions', TransactionController::class);

        Route::get('dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('dashboard/product-sales', [DashboardController::class, 'productSales']);
        Route::get('dashboard/store-sales', [DashboardController::class, 'storeSales']);
        Route::get('dashboard/store-online-sales', [DashboardController::class, 'storeOnlineSales']);
        Route::get('dashboard/store-daily-online-sales', [DashboardController::class, 'storeDailyOnlineSales']);
        Route::get('dashboard/store-daily-offline-sales', [DashboardController::class, 'storeDailyOfflineSales']);
        Route::get('dashboard/weekly-traffic', [DashboardController::class, 'weeklyTraffic']);
        Route::get('dashboard/top-products', [DashboardController::class, 'topProducts']);

        Route::apiResource('wifi-credentials', WifiCredentialController::class);

        // Admin-only routes
        Route::middleware('admin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{id}', [UserController::class, 'show']);
            Route::put('users/{id}', [UserController::class, 'update']);
            Route::delete('users/{id}', [UserController::class, 'destroy']);
        });

        // Plan-gated features
        Route::middleware('check.plan:expenses')->group(function () {
            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::get('expenses/{id}', [ExpenseController::class, 'show']);
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::put('expenses/{id}', [ExpenseController::class, 'update']);
            Route::delete('expenses/{id}', [ExpenseController::class, 'destroy']);
        });

        // Stock Opname (Pro plan)
        Route::middleware('check.plan:stock-opname')->group(function () {
            Route::get('stock-opnames', [StockOpnameController::class, 'index']);
            Route::post('stock-opnames', [StockOpnameController::class, 'store']);
            Route::get('stock-opnames/{id}', [StockOpnameController::class, 'show']);
            Route::put('stock-opnames/{id}', [StockOpnameController::class, 'update']);
            Route::delete('stock-opnames/{id}', [StockOpnameController::class, 'destroy']);
            Route::put('stock-opnames/{id}/items/{itemId}', [StockOpnameController::class, 'updateItem']);
            Route::post('stock-opnames/{id}/cancel', [StockOpnameController::class, 'cancel']);

            // Admin only: approve & reject
            Route::middleware('admin')->group(function () {
                Route::post('stock-opnames/{id}/approve', [StockOpnameController::class, 'approve']);
                Route::post('stock-opnames/{id}/reject', [StockOpnameController::class, 'reject']);
            });
        });

        Route::middleware('check.plan:expense-categories')->group(function () {
            Route::get('expense/categories', [ExpenseCategoryController::class, 'index']);
            Route::get('expense/categories/{id}', [ExpenseCategoryController::class, 'show']);
            Route::post('expense/categories', [ExpenseCategoryController::class, 'store']);
            Route::put('expense/categories/{id}', [ExpenseCategoryController::class, 'update']);
            Route::delete('expense/categories/{id}', [ExpenseCategoryController::class, 'destroy']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('sales-summary',    [ReportController::class, 'salesSummary']);
            Route::get('sales-by-type',    [ReportController::class, 'salesByType']);
            Route::get('sales-by-payment', [ReportController::class, 'salesByPayment']);
            Route::get('sales-by-store',   [ReportController::class, 'salesByStore']);
            Route::get('top-products',     [ReportController::class, 'topProducts']);
            Route::get('expense-summary',  [ReportController::class, 'expenseSummary']);
            Route::get('expense-by-category', [ReportController::class, 'expenseByCategory']);
            Route::get('profit-loss',      [ReportController::class, 'profitLoss']);
        });

        // Exports
        Route::prefix('export')->group(function () {
            Route::get('transactions',        [ExportController::class, 'exportTransactions']);
            Route::get('expenses',            [ExportController::class, 'exportExpenses']);
            Route::get('products',            [ExportController::class, 'exportProducts']);
            Route::get('employees',           [ExportController::class, 'exportEmployees']);
            Route::get('stores',              [ExportController::class, 'exportStores']);
            Route::get('product-categories',  [ExportController::class, 'exportProductCategories']);
            Route::get('expense-categories',  [ExportController::class, 'exportExpenseCategories']);
        });

        // Owner-only routes
        Route::middleware('owner')->group(function () {
            Route::get('owner/dashboard', [OwnerDashboardController::class, 'index']);

            Route::get('features', [FeatureController::class, 'index']);
            Route::get('plan-features', [FeatureController::class, 'planFeatures']);
            Route::put('plan-features', [FeatureController::class, 'updatePlanFeature']);

            Route::get('companies', [CompanyController::class, 'index']);
            Route::put('companies/{id}/plan', [CompanyController::class, 'updatePlan']);
            Route::post('companies/{id}/renew', [CompanyController::class, 'renew']);

            Route::get('invitation-codes', [InvitationCodeController::class, 'index']);
            Route::post('invitation-codes/generate', [InvitationCodeController::class, 'generate']);
            Route::delete('invitation-codes/{id}', [InvitationCodeController::class, 'destroy']);
        });
    });
});
