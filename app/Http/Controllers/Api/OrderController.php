<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
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

    /**
     * Pelanggan membatalkan pesanan yang belum dibayar.
     *
     * Bila transaksi Snap sudah dibuka, transaksinya dibatalkan juga di
     * Midtrans supaya tidak bisa dibayar belakangan dari tab yang lama.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->payment_status !== 'pending' || $order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini tidak dapat dibatalkan.',
            ], 422);
        }

        $serverKey = config('services.midtrans.server_key');

        if ($order->payment_method === 'midtrans_snap' && ! empty($serverKey)) {
            MidtransConfig::$serverKey = $serverKey;
            MidtransConfig::$isProduction = (bool) config(
                'services.midtrans.is_production',
                false
            );

            try {
                \Midtrans\Transaction::cancel($order->order_number);
            } catch (\Throwable $e) {
                $code = (int) $e->getCode();

                // 404: belum ada transaksi di Midtrans (metode bayar belum
                // dipilih), jadi tidak ada yang perlu dibatalkan di sana.
                if ($code !== 404) {
                    Log::warning('Midtrans cancel failed', [
                        'order_id' => $order->order_number,
                        'code' => $code,
                        'message' => $e->getMessage(),
                    ]);

                    // 412: transaksi sudah tidak berstatus pending (mis. sudah dibayar).
                    return response()->json([
                        'success' => false,
                        'message' => $code === 412
                            ? 'Pesanan ini sudah tidak dapat dibatalkan. Muat ulang halaman untuk melihat statusnya.'
                            : 'Pembatalan belum dapat diproses. Coba lagi sebentar lagi.',
                    ], $code === 412 ? 409 : 502);
                }
            }
        }

        $cancelled = DB::transaction(function () use ($order) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            // Status bisa berubah (mis. webhook pembayaran) sejak dibaca di atas.
            if (
                ! $fresh ||
                $fresh->payment_status !== 'pending' ||
                $fresh->status !== 'pending'
            ) {
                return null;
            }

            $fresh->status = 'cancelled';
            $fresh->payment_status = 'cancelled';
            $fresh->save();

            return $fresh;
        });

        if (! $cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Status pesanan baru saja berubah. Muat ulang halaman.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan dibatalkan.',
            'data' => $cancelled->load('items'),
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
