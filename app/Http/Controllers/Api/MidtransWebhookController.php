<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        foreach ([
            'order_id',
            'status_code',
            'gross_amount',
            'signature_key',
            'transaction_status',
        ] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifikasi tidak lengkap.',
                ], 400);
            }
        }

        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            Log::error('Midtrans webhook: Server Key is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi pembayaran belum tersedia.',
            ], 500);
        }

        // Verifikasi signature dari Midtrans.
        $signatureInput = $payload['order_id']
            . $payload['status_code']
            . $payload['gross_amount']
            . $serverKey;

        $expectedSignature = hash('sha512', $signatureInput);

        
        $signatureValid = hash_equals(
            $expectedSignature,
            $payload['signature_key']
        );

        if (! $signatureValid) {
            // Cari pesanan lokal terlebih dahulu.
            $localOrder = Order::where(
                'order_number',
                $payload['order_id']
            )->first();

            if (! $localOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan.',
                ], 404);
            }

            // Nominal notifikasi harus sesuai dengan pesanan lokal.
            $localAmount = (int) round(
                (float) $localOrder->total_amount * 100
            );

            $notificationAmount = (int) round(
                (float) $payload['gross_amount'] * 100
            );

            if ($localAmount !== $notificationAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal tidak cocok.',
                ], 422);
            }

            // Verifikasi ulang langsung ke Midtrans menggunakan
            // Server Key yang dibaca oleh backend Laravel.
            \Midtrans\Config::$serverKey = $serverKey;
            \Midtrans\Config::$isProduction = (bool) config(
                'services.midtrans.is_production',
                false
            );

            try {
                $statusResponse = \Midtrans\Transaction::status(
                    $localOrder->order_number
                );

                $verified = is_array($statusResponse)
                    ? $statusResponse
                    : (array) $statusResponse;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    'Midtrans status verification failed',
                    [
                        'order_id' => $localOrder->order_number,
                        'exception' => $e::class,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Verifikasi status transaksi gagal.',
                ], 502);
            }

            $verifiedAmount = (int) round(
                (float) ($verified['gross_amount'] ?? 0) * 100
            );

            // Harus cocok dengan transaksi yang tersimpan di Midtrans.
            if (
                (string) ($verified['order_id'] ?? '') !== $localOrder->order_number ||
                (string) ($verified['status_code'] ?? '') !== '200' ||
                $verifiedAmount !== $localAmount
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data transaksi tidak cocok dengan Status API Midtrans.',
                ], 403);
            }

            // Gunakan status hasil verifikasi server-ke-server,
            // bukan status dari payload yang signature-nya tidak cocok.
            $payload['transaction_status'] =
                (string) ($verified['transaction_status'] ?? '');

            $payload['fraud_status'] =
                (string) ($verified['fraud_status'] ?? '');

            $payload['gross_amount'] =
                (string) $verified['gross_amount'];

            $payload['status_code'] =
                (string) $verified['status_code'];
        }


        $order = DB::transaction(function () use ($payload) {
            $order = Order::where(
                'order_number',
                $payload['order_id']
            )->lockForUpdate()->first();

            if (! $order) {
                return null;
            }

            // Pastikan nominal notifikasi cocok dengan pesanan.
            $notifiedAmount = (int) round(
                (float) $payload['gross_amount'] * 100
            );

            $expectedAmount = (int) round(
                (float) $order->total_amount * 100
            );

            if ($notifiedAmount !== $expectedAmount) {
                return false;
            }

            $transactionStatus = $payload['transaction_status'];
            $fraudStatus = strtolower(
                (string) ($payload['fraud_status'] ?? '')
            );

            // Jangan menurunkan status pesanan yang sudah dibayar.
            if ($order->payment_status === 'paid') {
                return $order;
            }

            if (
                $transactionStatus === 'settlement' ||
                (
                    $transactionStatus === 'capture' &&
                    ($fraudStatus === '' || $fraudStatus === 'accept')
                )
            ) {
                $order->payment_status = 'paid';
                $order->paid_at = now();

                if ($order->status === 'pending') {
                    $order->status = 'processing';
                }
            } elseif ($transactionStatus === 'pending') {
                $order->payment_status = 'pending';
            } elseif ($transactionStatus === 'expire') {
                $order->payment_status = 'expired';
                $order->status = 'cancelled';
            } elseif ($transactionStatus === 'cancel') {
                $order->payment_status = 'cancelled';
                $order->status = 'cancelled';
            } elseif ($transactionStatus === 'deny') {
                $order->payment_status = 'failed';
            }

            $order->save();

            return $order;
        });

        if ($order === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        if ($order === false) {
            Log::warning('Midtrans webhook: amount mismatch.', [
                'order_id' => $payload['order_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nominal pembayaran tidak cocok.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pembayaran berhasil diproses.',
        ]);
    }
}
