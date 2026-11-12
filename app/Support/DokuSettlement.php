<?php

namespace App\Support;

use App\Models\Order;
use App\Notifications\OrderPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menerapkan hasil pembayaran DOKU ke pesanan. Dipakai bersama oleh webhook
 * dan pengecekan status langsung, supaya aturannya satu dan sama.
 */
class DokuSettlement
{
    /**
     * @return array{order: Order|false|null, paid: bool}
     *   order: null = pesanan tak ditemukan, false = nominal tidak cocok.
     *   paid:  true hanya saat pesanan baru saja berubah menjadi lunas.
     */
    public static function apply(string $invoice, mixed $amount, string $status): array
    {
        $justPaid = false;
        $status = strtoupper($status);

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

        return ['order' => $order, 'paid' => $justPaid];
    }

    /** Email bukti bayar, dikirim setelah respons selesai agar SMTP lambat tidak menahan request. */
    public static function sendPaidEmail(int $orderId): void
    {
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
}
