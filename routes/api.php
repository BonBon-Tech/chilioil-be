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


Route::prefix('/v1')->group(function () {
    Route::prefix('/auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('jwt')->group(function () {
            Route::get('/user', [AuthController::class, 'getUser']);
            Route::put('/user', [AuthController::class, 'updateUser']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::prefix('/users')->group(function () {
        Route::middleware(['jwt', 'admin'])->group(function () {
            Route::get('', [UserController::class, 'index']);
            Route::post('', [UserController::class, 'store']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });
    });

    Route::middleware('jwt')->group(function () {
        Route::post('images', [ImageController::class, 'store']);
    });

    Route::middleware(['jwt'])->group(function () {
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
        Route::delete('products/{id}', [ProductController::class, 'destroy']);

        Route::get('expense/categories', [ExpenseCategoryController::class, 'index']);
        Route::get('expense/categories/{id}', [ExpenseCategoryController::class, 'show']);
        Route::post('expense/categories', [ExpenseCategoryController::class, 'store']);
        Route::put('expense/categories/{id}', [ExpenseCategoryController::class, 'update']);
        Route::delete('expense/categories/{id}', [ExpenseCategoryController::class, 'destroy']);

        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::get('expenses/{id}', [ExpenseController::class, 'show']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::put('expenses/{id}', [ExpenseController::class, 'update']);
        Route::delete('expenses/{id}', [ExpenseController::class, 'destroy']);

        Route::apiResource('transactions', TransactionController::class);
    });
});
