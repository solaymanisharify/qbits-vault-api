<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashInController;
use App\Http\Controllers\CashOutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReconcileController;
use App\Http\Controllers\RoleAndPermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

// auth routes
Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');
});

Route::middleware('auth:api')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('user/{id}', [UserController::class, 'show']);
    Route::put('user/{id}', [UserController::class, 'update']);
    Route::post('users', [UserController::class, 'create']);
    Route::post('users/{userId}/assign-role', [UserController::class, 'assignRole']);
    Route::post('users/{userId}/assign-permission', [UserController::class, 'assignPermission']);
    Route::post('users/change-password/{userId}', [AuthController::class, 'changePassword']);

    // cashin
    Route::apiResource('/cash-in', CashInController::class);
    Route::get('/pending/cash-in', [CashInController::class, 'listPending']);
    Route::post('/verify/cash-in/{cashInId}', [CashInController::class, 'verify']);
    Route::post('/approve/cash-in/{cashInId}', [CashInController::class, 'approved']);
    // role
    Route::apiResource('/roles', RoleController::class);

    // cashout
    Route::apiResource('/cash-out', CashOutController::class);
    Route::get('/pending/cash-out', [CashOutController::class, 'listPending']);
    Route::post('/verify/cash-out/{cashOutId}', [CashOutController::class, 'verify']);
    Route::post('/approve/cash-out/{cashOutId}', [CashOutController::class, 'approved']);

    //permissions
    Route::apiResource('/permissions', PermissionController::class);

    Route::apiResource('vault', VaultController::class);
    // Route::get('/bag/{id}', [VaultController::class, 'getBag']);
    Route::get('/bag/{bagId}', [VaultController::class, 'getBagByBagId']);

    Route::get('/get-all-orders', [OrderController::class, 'index']);

    // reconcile
    Route::get('/reconciles', [ReconcileController::class, 'index']);
    Route::get('/reconcile/latest', [ReconcileController::class, 'latestReconcile']);
    Route::post('/reconcile', [ReconcileController::class, 'create']);
    Route::get('/pending/reconciles', [ReconcileController::class, 'listPending']);
    Route::post('/reconcile/verify/{reconcileId}', [ReconcileController::class, 'verify']);
    Route::post('/reconcile/approve/{reconcileId}', [ReconcileController::class, 'approved']);
    Route::post('/reconcile/start/{reconcileId}', [ReconcileController::class, 'startReconcile']);
    Route::get('/reconciliation/check', [ReconcileController::class, 'checkReconcile']);
    Route::put('/reconciliation/complete/{reconcileId}', [ReconcileController::class, 'completeReconcile']);

    //dashboard reports
    Route::get('/dashboard/reports', [DashboardController::class, 'index']);
});
