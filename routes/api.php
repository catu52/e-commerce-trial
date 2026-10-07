<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\StaffAuthController;

use App\Http\Controllers\CMS\OrderController;
use App\Http\Controllers\CMS\ProductController;
use App\Http\Controllers\CMS\RoleController;
use App\Http\Controllers\CMS\UserController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Customer Buyer Auth
Route::prefix('v1/client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::post('/login', [ClientAuthController::class, 'login']);
});

// Staff CMS Auth
Route::prefix('v1/staff')->group(function () {
    Route::post('/login', [StaffAuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Protected Buyer Routes (Sanctum Client Guard)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum_clients'])->prefix('v1/client')->group(function () {
    Route::post('/logout', [ClientAuthController::class, 'logout']);
    
    // Flash sale reservation endpoints will go here...
});

/*
|--------------------------------------------------------------------------
| Protected Staff CMS Routes (Sanctum User Guard)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum_users'])->prefix('v1/staff')->group(function () {
    Route::get('/me', [StaffAuthController::class, 'profile']);
    Route::post('/logout', [StaffAuthController::class, 'logout']);

    // User & Staff Management
    Route::apiResource('users', UserController::class);

    // Roles & Permissions Matrix
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

    // Products & Stock Management
    Route::apiResource('products', ProductController::class)->except(['destroy']);
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);

    // Order Fulfillment & CS Operations
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
});
