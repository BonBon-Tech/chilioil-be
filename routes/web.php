<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ImageController;

Route::get('/', function () {
    return view('welcome');
});
Route::put('users/{id}/role', [UserController::class, 'updateRole']);

Route::middleware(['auth', 'admin.only'])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    Route::resource('roles', RoleController::class);
});

Route::middleware('auth')->group(function () {
    Route::post('images', [ImageController::class, 'store']);
});



