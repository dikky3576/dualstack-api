<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CouponResolver;
use Illuminate\Validation\ValidationException;
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
            $fresh->releaseCoupon();

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

    /**
     * Memasang kupon pada pesanan yang belum dibayar.
     *
     * Hanya sebelum pembayaran dimulai: setelah token Snap dibuat, nominal
     * transaksi di Midtrans tidak bisa diubah lagi.
     */
    public function applyCoupon(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $order = DB::transaction(function () use ($request, $id, $validated) {
            $order = Order::where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCouponEditable($order);

            $base = (int) round(
                ((float) $order->subtotal - (float) $order->discount_amount) * 100
            );

            [$coupon, $cents] = CouponResolver::resolve(
                $validated['code'],
                $request->user(),
                $base,
                true,
                $order->id
            );

            if ($order->coupon_id !== $coupon->id) {
                $order->releaseCoupon();
                $coupon->increment('used_count');
            }

            $order->coupon_id = $coupon->id;
            $order->coupon_code = $coupon->code;
            $order->coupon_discount = $cents / 100;
            $order->total_amount = ($base - $cents) / 100;

            // Kupon menutup seluruh harga: tidak perlu bayar, sama seperti
            // pesanan gratis.
            if ($base - $cents === 0) {
                $order->status = 'completed';
                $order->payment_status = 'paid';
                $order->paid_at = now();
            }

            $order->save();

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Kupon dipasang.',
            'data' => $order->load('items'),
        ]);
    }

    public function removeCoupon(Request $request, string $id): JsonResponse
    {
        $order = DB::transaction(function () use ($request, $id) {
            $order = Order::where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCouponEditable($order);

            if ($order->coupon_id) {
                $order->releaseCoupon();
            }

            $order->coupon_id = null;
            $order->coupon_code = null;
            $order->coupon_discount = 0;
            $order->total_amount = (float) $order->subtotal - (float) $order->discount_amount;
            $order->save();

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Kupon dilepas.',
            'data' => $order->load('items'),
        ]);
    }

    private function assertCouponEditable(Order $order): void
    {
        if ($order->status !== 'pending' || $order->payment_status !== 'pending') {
            throw ValidationException::withMessages([
                'coupon_code' => ['Kupon hanya bisa diubah pada pesanan yang belum dibayar.'],
            ]);
        }

        if ($order->payment_method !== null) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Pembayaran sudah dimulai, jadi kupon tidak bisa diubah lagi. Buat pesanan baru bila ingin memakai kupon lain.'],
            ]);
        }
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

        // Midtrans mewajibkan jumlah item_details sama dengan gross_amount,
        // jadi potongan kupon dikirim sebagai item bernilai negatif.
        $couponDiscount = (int) round((float) $order->coupon_discount);

        if ($couponDiscount > 0) {
            $itemDetails[] = [
                'id' => 'COUPON',
                'price' => -$couponDiscount,
                'quantity' => 1,
                'name' => substr('Kupon ' . $order->coupon_code, 0, 50),
            ];
        }

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
