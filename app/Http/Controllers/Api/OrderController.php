<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items:id,order_id,product_name,product_slug,quantity,unit_price,discount_amount,line_total')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pesanan berhasil diambil.',
            'data' => $orders,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items:id,order_id,product_name,product_slug,quantity,unit_price,discount_amount,line_total')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail pesanan berhasil diambil.',
            'data' => $order,
        ]);
    }

    public function payment(Request $request, string $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->findOrFail($id);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini sudah dibayar.',
            ], 422);
        }

        if ($order->status !== 'pending' || (float) $order->total_amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak memenuhi syarat pembayaran.',
            ], 422);
        }

        $serverKey = config('services.midtrans.server_key');
        $clientKey = config('services.midtrans.client_key');

        if (empty($serverKey) || empty($clientKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi pembayaran belum tersedia.',
            ], 503);
        }

        MidtransConfig::$serverKey = $serverKey;
        MidtransConfig::$isProduction = (bool) config(
            'services.midtrans.is_production',
            false
        );
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;

        $grossAmount = (int) round((float) $order->total_amount);

        $itemDetails = $order->items->map(function ($item) {
            return [
                'id' => (string) $item->product_id,
                'price' => (int) round((float) $item->line_total),
                'quantity' => 1,
                'name' => substr($item->product_name, 0, 50),
            ];
        })->values()->all();

        try {
            $transaction = Snap::createTransaction([
                'transaction_details' => [
                    'order_id' => $order->order_number,
                    'gross_amount' => $grossAmount,
                ],
                'item_details' => $itemDetails,
                'customer_details' => [
                    'first_name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ]);

            $order->payment_method = 'midtrans_snap';
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi pembayaran berhasil dibuat.',
                'data' => [
                    'order_number' => $order->order_number,
                    'gross_amount' => $grossAmount,
                    'token' => $transaction->token,
                    'redirect_url' => $transaction->redirect_url,
                    'client_key' => $clientKey,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Midtrans Snap transaction creation failed', [
                'order_id' => $order->order_number,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat transaksi pembayaran. Periksa log server.',
            ], 502);
        }
    }
}
