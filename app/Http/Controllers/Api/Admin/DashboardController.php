<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // Statistik pengguna
        $totalCustomers = User::where('role', 'customer')->count();

        // Statistik kategori
        $totalCategories = Category::count();
        $activeCategories = Category::where('is_active', true)->count();

        // Statistik produk
        $totalProducts = Product::count();

        $publishedProducts = Product::where(
            'status',
            'published'
        )->count();

        $draftProducts = Product::where(
            'status',
            'draft'
        )->count();

        // Statistik pesanan
        $totalOrders = Order::count();

        $pendingOrders = Order::where(
            'payment_status',
            'pending'
        )->count();

        $paidOrders = Order::where(
            'payment_status',
            'paid'
        )->count();

        $cancelledOrders = Order::where(
            'status',
            'cancelled'
        )->count();

        // Pendapatan hanya dari pembayaran terverifikasi
        $totalRevenue = Order::where(
            'payment_status',
            'paid'
        )->sum('total_amount');

        // Lima pesanan terbaru
        $recentOrders = Order::with([
            'user:id,name,email',
            'items:id,order_id,product_name,product_slug,quantity,line_total',
        ])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard administrator berhasil diambil.',
            'data' => [
                'customers' => [
                    'total' => $totalCustomers,
                ],

                'categories' => [
                    'total' => $totalCategories,
                    'active' => $activeCategories,
                ],

                'products' => [
                    'total' => $totalProducts,
                    'published' => $publishedProducts,
                    'draft' => $draftProducts,
                ],

                'orders' => [
                    'total' => $totalOrders,
                    'pending' => $pendingOrders,
                    'paid' => $paidOrders,
                    'cancelled' => $cancelledOrders,
                ],

                'revenue' => [
                    'total' => (float) $totalRevenue,
                    'currency' => 'IDR',
                ],

                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}
