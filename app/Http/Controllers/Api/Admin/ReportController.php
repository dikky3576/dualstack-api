<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : $endDate->copy()->subDays(29)->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal mulai tidak boleh melewati tanggal akhir.',
            ], 422);
        }

        // Pesanan yang dibuat pada periode laporan.
        $createdOrders = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Pembayaran yang telah diverifikasi pada periode laporan.
        $paidOrders = Order::query()
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate]);

        $summary = [
            'orders_created' => (clone $createdOrders)->count(),

            'pending_orders' => (clone $createdOrders)
                ->where('payment_status', 'pending')
                ->count(),

            'cancelled_orders' => (clone $createdOrders)
                ->where('status', 'cancelled')
                ->count(),

            'paid_orders' => (clone $paidOrders)->count(),

            'total_revenue' => (float) (
                (clone $paidOrders)->sum('total_amount')
            ),

            'currency' => 'IDR',
        ];

        // Pendapatan dikelompokkan berdasarkan tanggal pembayaran.
        $dailyRevenue = Order::query()
            ->selectRaw('DATE(paid_at) as date')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as revenue')
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->groupByRaw('DATE(paid_at)')
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'orders_count' => (int) $row->orders_count,
                    'revenue' => (float) $row->revenue,
                ];
            });

        // Produk terlaris berdasarkan item dalam pesanan yang sudah dibayar.
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$startDate, $endDate])
            ->select(
                'order_items.product_id',
                'order_items.product_name'
            )
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy(
                'order_items.product_id',
                'order_items.product_name'
            )
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'units_sold' => (int) $item->units_sold,
                    'revenue' => (float) $item->revenue,
                ];
            });

        // Sepuluh pembayaran terbaru.
        $recentPaidOrders = Order::query()
            ->with([
                'user:id,name,email',
                'items:id,order_id,product_name,quantity,line_total',
            ])
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->latest('paid_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Laporan penjualan berhasil diambil.',
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'summary' => $summary,
                'daily_revenue' => $dailyRevenue,
                'top_products' => $topProducts,
                'recent_paid_orders' => $recentPaidOrders,
            ],
        ]);
    }
}
