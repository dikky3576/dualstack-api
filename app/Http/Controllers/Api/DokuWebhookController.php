<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderPaid;
use App\Support\DokuClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi pembayaran dari DOKU Checkout.
 *
 * Alamat yang didaftarkan di DOKU Back Office (Notification URL):
 *   https://api.dualstack.my.id/api/doku/notification
 */
class DokuWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! DokuClient::enabled()) {
            Log::error('DOKU webhook: Client ID / Secret Key is not configured.');

            return response()->json(['success' => false, 'message' => 'Konfigurasi pembayaran belum tersedia.'], 500);
        }

        // Request-Target = path alamat notifikasi persis seperti yang didaftarkan.
        $target = (string) config('services.doku.notification_path')
            ?: '/'.ltrim($request->path(), '/');

        $valid = DokuClient::verifyNotification(
            $request->getContent(),
            $request->header('Client-Id'),
            $request->header('Request-Id'),
            $request->header('Request-Timestamp'),
            $request->header('Signature'),
            $target,
        );

        if (! $valid) {
            Log::warning('DOKU webhook: invalid signature.', ['target' => $target]);

            return response()->json(['success' => false, 'message' => 'Tanda tangan tidak valid.'], 401);
        }

        $invoice = data_get($request->json()->all(), 'order.invoice_number');
        $amount = data_get($request->json()->all(), 'order.amount');
        $status = strtoupper((string) data_get($request->json()->all(), 'transaction.status'));

        if (! is_string($invoice) || $invoice === '' || ! is_numeric($amount) || $status === '') {
            return response()->json(['success' => false, 'message' => 'Notifikasi tidak lengkap.'], 400);
        }

        $justPaid = false;

        $order = DB::transaction(function () use ($invoice, $amount, $status, &$justPaid) {
            $order = Order::where('order_number', $invoice)->lockForUpdate()->first();

            if (! $order) {
                return null;
            }

            if ((int) round((float) $amount) !== (int) round((float) $order->total_amount)) {
                return false;
            }

            // Jangan menurunkan pesanan yang sudah dibayar.
            if ($order->payment_status === 'paid') {
                if (in_array($order->status, ['pending', 'processing'], true)) {
                    $order->status = 'completed';
                    $order->save();
                }

                return $order;
            }

            if ($status === 'SUCCESS') {
                $order->payment_status = 'paid';
                $order->paid_at = now();
                $justPaid = true;

                // Dibayar dari halaman lama setelah dibatalkan: ambil lagi kuota kupon.
                if ($order->status === 'cancelled') {
                    $order->consumeCoupon();
                }

                // Produk digital: yang sudah dibayar langsung selesai.
                if (in_array($order->status, ['pending', 'processing', 'cancelled'], true)) {
                    $order->status = 'completed';
                }
            } elseif ($status === 'EXPIRED') {
                $order->payment_status = 'expired';

                if ($order->status !== 'cancelled') {
                    $order->releaseCoupon();
                }

                $order->status = 'cancelled';
            } elseif ($status === 'FAILED') {
                $order->payment_status = 'failed';
            }

            $order->save();

            return $order;
        });

        if ($order === null) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($order === false) {
            Log::warning('DOKU webhook: amount mismatch.', ['invoice' => $invoice]);

            return response()->json(['success' => false, 'message' => 'Nominal pembayaran tidak cocok.'], 422);
        }

        if ($justPaid) {
            $orderId = $order->id;

            dispatch(function () use ($orderId) {
                try {
                    $paid = Order::with(['items', 'user'])->find($orderId);

                    $paid?->user?->notify(new OrderPaid($paid));
                } catch (\Throwable $e) {
                    Log::error('Order paid email failed', [
                        'order_id' => $orderId,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }

        return response()->json(['success' => true, 'message' => 'Notifikasi pembayaran berhasil diproses.']);
    }
}
