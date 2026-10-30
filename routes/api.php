<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\ProductMediaController;
use App\Http\Controllers\Api\Admin\ReportController;


use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\ProductDownloadController;
use App\Http\Controllers\Api\CustomerDashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CustomerWishlistController;
use App\Http\Controllers\Api\ProductReviewController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [
            AdminDashboardController::class,
            'index',
        ]);

        Route::apiResource(
            'categories',
            AdminCategoryController::class
        );

        Route::apiResource(
            'products',
            AdminProductController::class
        );

        Route::get('orders', [AdminOrderController::class, 'index']);

        Route::get('orders/{id}', [AdminOrderController::class, 'show']);

        Route::patch('orders/{id}/status', [
            AdminOrderController::class,
            'updateStatus',
        ]);
        
        Route::get('/users', [AdminUserController::class, 'index']);

        Route::get('/users/{id}', [AdminUserController::class, 'show']);

        Route::put('/users/{id}', [AdminUserController::class, 'update']);

        Route::patch('/users/{id}/role', [
            AdminUserController::class,
            'updateRole',
        ]);

        Route::post('/products/{productId}/media', [
            ProductMediaController::class,
            'store',
        ]);

        Route::get('/reports/sales', [
            ReportController::class,
            'sales',
        ]);
    });

Route::middleware('auth:sanctum')
    ->prefix('customer')
    ->group(function () {

        Route::get('/profile', [
            ProfileController::class,
            'show',
        ]);

        Route::put('/profile', [
            ProfileController::class,
            'update',
        ]);

        Route::put('/profile/password', [
            ProfileController::class,
            'updatePassword',
        ]);

        Route::get('/wishlist', [
            CustomerWishlistController::class,
            'index',
        ]);

        Route::post('/wishlist', [
            CustomerWishlistController::class,
            'store',
        ]);

        Route::delete('/wishlist/{productId}', [
            CustomerWishlistController::class,
            'destroy',
        ]);

        // Manajemen review pelanggan.
        Route::post('/reviews', [
            ProductReviewController::class,
            'store',
        ]);

        Route::put('/reviews/{id}', [
            ProductReviewController::class,
            'update',
        ]);

        Route::delete('/reviews/{id}', [
            ProductReviewController::class,
            'destroy',
        ]);
    });

Route::middleware('auth:sanctum')->post(
    '/checkout',
    [CheckoutController::class, 'store']
);

Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{id}', [OrderController::class, 'show']);
    Route::post('/{id}/payment', [OrderController::class, 'payment']);
});

Route::post('/midtrans/notification', [
    MidtransWebhookController::class,
    'handle',
]);

Route::middleware('auth:sanctum')->get(
    '/products/{id}/download',
    [ProductDownloadController::class, 'download']
);

Route::middleware('auth:sanctum')->get(
    '/customer/dashboard',
    [CustomerDashboardController::class, 'index']
);

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Melihat ulasan produk.
Route::get('/products/{productId}/reviews', [
    ProductReviewController::class,
    'index',
]);