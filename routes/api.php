<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\ProductMediaController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;


use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\SitemapController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\Admin\ViewStatsController;
use App\Http\Controllers\Api\ProjectRequestController;
use App\Http\Controllers\Api\Admin\ProjectRequestController as AdminProjectRequestController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\ProductDownloadController;
use App\Http\Controllers\Api\CustomerDashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CustomerWishlistController;
use App\Http\Controllers\Api\CustomerDownloadController;
use App\Http\Controllers\Api\ProductReviewController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google', [GoogleAuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // Lupa password: kirim link ke email, lalu atur password baru.
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:5,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1');

    // Verifikasi email: link dari email (tanpa login) dan kirim ulang.
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:3,1');

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

        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

        Route::patch('/users/{id}/role', [
            AdminUserController::class,
            'updateRole',
        ]);

        // Kupon diskon. Rute bulk sebelum apiResource agar tidak tertukar.
        Route::post('coupons/bulk', [AdminCouponController::class, 'bulk']);
        Route::apiResource('coupons', AdminCouponController::class)
            ->except(['show']);

        // Moderasi ulasan.
        Route::get('/reviews', [AdminReviewController::class, 'index']);

        Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

        Route::post('/products/{productId}/media', [
            ProductMediaController::class,
            'store',
        ]);

        Route::get('/stats/views', [ViewStatsController::class, 'index']);

        // Permintaan proyek dari halaman Jasa.
        Route::get('/project-requests', [AdminProjectRequestController::class, 'index']);
        Route::put('/project-requests/{id}', [AdminProjectRequestController::class, 'update']);
        Route::delete('/project-requests/{id}', [AdminProjectRequestController::class, 'destroy']);

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

        // ID semua produk di wishlist (harus sebelum /wishlist/{productId}).
        Route::get('/wishlist/ids', [
            CustomerWishlistController::class,
            'ids',
        ]);

        // Apakah produk ini sudah ada di wishlist pelanggan.
        Route::get('/wishlist/{productId}', [
            CustomerWishlistController::class,
            'show',
        ]);

        Route::post('/wishlist', [
            CustomerWishlistController::class,
            'store',
        ]);

        // Template gratis yang pernah diunduh pelanggan.
        Route::get('/downloads', [
            CustomerDownloadController::class,
            'index',
        ]);

        Route::delete('/wishlist/{productId}', [
            CustomerWishlistController::class,
            'destroy',
        ]);

        // Manajemen review pelanggan.
        Route::get('/reviews', [
            ProductReviewController::class,
            'mine',
        ]);

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
    Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/{id}/coupon', [OrderController::class, 'applyCoupon'])
        ->middleware('throttle:20,1');
    Route::delete('/{id}/coupon', [OrderController::class, 'removeCoupon']);
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

// Sitemap untuk mesin pencari (dirujuk dari robots.txt frontend).
Route::get('/sitemap.xml', [SitemapController::class, 'index'])
    ->middleware('throttle:60,1');

// Formulir permintaan proyek (publik, dibatasi agar tidak jadi sasaran spam).
Route::post('/project-requests', [ProjectRequestController::class, 'store'])
    ->middleware('throttle:5,10');

// Pencatat kunjungan (hitungan harian, tanpa data pribadi).
Route::post('/track', [TrackController::class, 'store'])
    ->middleware('throttle:120,1');

// Kupon publik untuk banner beranda.
Route::get('/promos', [PromoController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Melihat ulasan produk.
Route::get('/products/{productId}/reviews', [
    ProductReviewController::class,
    'index',
]);