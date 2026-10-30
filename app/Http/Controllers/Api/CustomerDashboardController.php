<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $orders = Order::query()
            ->where('user_id', $userId);

        $totalOrders = (clone $orders)->count();

        $pendingOrders = (clone $orders)
            ->where('payment_status', 'pending')
            ->count();

        $paidOrders = (clone $orders)
            ->where('payment_status', 'paid')
            ->count();

        $totalSpent = (clone $orders)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $recentOrders = Order::query()
            ->where('user_id', $userId)
            ->with([
                'items:id,order_id,product_name,product_slug,quantity,unit_price,line_total',
            ])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard pelanggan berhasil diambil.',
            'data' => [
                'summary' => [
                    'total_orders' => $totalOrders,
                    'pending_orders' => $pendingOrders,
                    'paid_orders' => $paidOrders,
                    'total_spent' => (float) $totalSpent,
                    'currency' => 'IDR',
                ],
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}
