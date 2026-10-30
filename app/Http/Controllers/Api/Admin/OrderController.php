<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with([
            'user:id,name,email',
            'items:id,order_id,product_name,product_slug,quantity,unit_price,line_total',
        ])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('payment_status'), function ($query) use ($request) {
                $query->where('payment_status', $request->input('payment_status'));
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with([
            'user:id,name,email',
            'items:id,order_id,product_name,product_slug,quantity,unit_price,line_total',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['pending', 'processing', 'completed', 'cancelled']),
            ],
        ]);

        $order = Order::findOrFail($id);

        // Status pesanan tidak boleh digunakan untuk
        // memalsukan pembayaran yang belum diverifikasi.
        if (
            $validated['status'] === 'completed' &&
            $order->payment_status !== 'paid'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak dapat diselesaikan sebelum pembayaran terverifikasi.',
            ], 422);
        }

        if ($order->status === 'cancelled' && $validated['status'] !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan yang dibatalkan tidak dapat diaktifkan kembali melalui endpoint ini.',
            ], 422);
        }

        $order->status = $validated['status'];
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => $order->fresh(),
        ]);
    }
}
