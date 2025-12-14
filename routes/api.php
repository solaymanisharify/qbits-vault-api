<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashInController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;



// auth routes
Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');
});

Route::middleware('auth:api')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'create']);
    Route::post('users/{userId}/assign-role', [UserController::class, 'assignRole']);
    Route::post('users/{userId}/assign-permission', [UserController::class, 'assignPermission']);

    // cashin
    Route::apiResource('/cash-in', CashInController::class);
});


Route::get('/get-all-orders', [OrderController::class, 'index']);
Route::apiResource('vault', VaultController::class);
