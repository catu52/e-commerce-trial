<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\FlashSaleController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\ClientOrderController;
use App\Http\Controllers\CMS\ClientController;
use App\Http\Controllers\CMS\OrderController;
use App\Http\Controllers\CMS\ProductController;
use App\Http\Controllers\CMS\RoleController;
use App\Http\Controllers\CMS\UserController;
use App\Http\Controllers\StaffAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Public Catalog
Route::prefix('v1/catalog')->group(function () {
    Route::get('/products', [CatalogController::class, 'index']);
    Route::get('/products/{slug}', [CatalogController::class, 'show']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/flash-sales', [FlashSaleController::class, 'index']);
});

// Public editorial content (managed in the Filament CMS)
Route::prefix('v1/content')->middleware('throttle:120,1')->group(function () {
    Route::get('/home', [ContentController::class, 'home']);
    Route::get('/site-settings', [ContentController::class, 'siteSettings']);
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{slug}', [ArticleController::class, 'show']);
    // Staff-only preview of unpublished articles; access comes from a temporary signed URL minted in the CMS.
    Route::get('/articles/{slug}/preview', [ArticleController::class, 'preview'])
        ->middleware('signed:relative')
        ->name('articles.preview');
});

Route::post('v1/newsletter/flash-drops', [NewsletterController::class, 'flashDrops'])
    ->middleware('throttle:'.config('flash_sale.newsletter_throttle'));

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
    Route::get('/me', [ClientAuthController::class, 'me']);
    Route::post('/logout', [ClientAuthController::class, 'logout']);

    // Checkout & order history
    Route::get('/orders', [ClientOrderController::class, 'index']);
    Route::post('/orders', [ClientOrderController::class, 'store']);
    Route::get('/orders/{orderNumber}', [ClientOrderController::class, 'show']);

    // Flash sale reservation endpoints will go here...
    Route::post('/flash-sales/{flashSaleId}/reserve', [ReservationController::class, 'store']);
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

    // Customers / Clients
    Route::apiResource('clients', ClientController::class);
    Route::post('/clients/{client}/password', [ClientController::class, 'setPassword']);
    Route::post('/clients/{client}/revoke-sessions', [ClientController::class, 'revokeSessions']);
    Route::get('/clients/{client}/orders', [ClientController::class, 'orders']);

    // Roles & Permissions Matrix
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

    // Products & Stock Management
    Route::apiResource('products', ProductController::class)->except(['destroy']);
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);
    Route::post('/products/{product}/images', [ProductController::class, 'uploadImage']);
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'deleteImage']);

    // Order Fulfillment & CS Operations
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
});
