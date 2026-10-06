<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\ClientAuthController;

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

    // Admin and campaign management endpoints will go here...
});
