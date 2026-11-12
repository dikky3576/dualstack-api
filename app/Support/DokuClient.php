<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien tipis untuk DOKU Checkout (halaman pembayaran hosted).
 *
 * Tanda tangan: HMACSHA256 dari komponen Client-Id, Request-Id,
 * Request-Timestamp, Request-Target dan Digest (SHA-256 body, base64),
 * dikunci dengan Secret Key.
 */
class DokuClient
{
    public const CHECKOUT_PATH = '/checkout/v1/payment';

    /** Masa berlaku invoice, dalam menit. */
    public const DUE_MINUTES = 60;

    public static function enabled(): bool
    {
        return filled(config('services.doku.client_id'))
            && filled(config('services.doku.secret_key'));
    }

    public static function baseUrl(): string
    {
        return config('services.doku.is_production')
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com';
    }

    public static function signature(
        string $clientId,
        string $requestId,
        string $timestamp,
        string $target,
        ?string $body,
        string $secret,
    ): string {
        $lines = [
            'Client-Id:'.$clientId,
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$target,
        ];

        // Request tanpa body (GET) tidak memakai Digest.
        if ($body !== null) {
            $lines[] = 'Digest:'.base64_encode(hash('sha256', $body, true));
        }

        $component = implode("\n", $lines);

        return 'HMACSHA256='.base64_encode(
            hash_hmac('sha256', $component, $secret, true)
        );
    }

    /**
     * Membuat invoice di DOKU.
     *
     * @return array{0: string, 1: string} [URL halaman bayar, Request-Id yang dipakai]
     * @throws RuntimeException bila DOKU menolak atau tidak terjangkau
     */
    public static function createCheckout(Order $order, User $user): array
    {
        $clientId = (string) config('services.doku.client_id');
        $secret = (string) config('services.doku.secret_key');

        $amount = (int) round((float) $order->total_amount);

        $orderPayload = [
            'amount' => $amount,
            'invoice_number' => $order->order_number,
            'callback_url' => rtrim((string) config('app.frontend_url'), '/')
                .'/checkout/'.$order->id.'?return=doku',
            'auto_redirect' => true,
        ];

        // Rincian item hanya dikirim bila totalnya persis sama dengan amount
        // (DOKU menolak bila tidak). Dengan kupon, cukup kirim amount akhir.
        $lineItems = $order->items->map(fn ($item) => [
            'name' => mb_substr((string) $item->product_name, 0, 100),
            'price' => (int) round((float) $item->line_total),
            'quantity' => 1,
        ])->values()->all();

        if ($lineItems !== [] && array_sum(array_column($lineItems, 'price')) === $amount) {
            $orderPayload['line_items'] = $lineItems;
        }

        $body = json_encode([
            'order' => $orderPayload,
            'payment' => ['payment_due_date' => self::DUE_MINUTES],
            'customer' => [
                'name' => mb_substr((string) $user->name, 0, 100),
                'email' => $user->email,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $requestId = (string) \Illuminate\Support\Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        $response = Http::withHeaders([
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => self::signature(
                $clientId,
                $requestId,
                $timestamp,
                self::CHECKOUT_PATH,
                $body,
                $secret,
            ),
        ])
            ->withBody($body, 'application/json')
            ->timeout(20)
            ->post(self::baseUrl().self::CHECKOUT_PATH);

        $url = $response->json('response.payment.url');

        if (! $response->successful() || ! is_string($url) || $url === '') {
            throw new RuntimeException(
                'DOKU menolak pembuatan invoice (HTTP '.$response->status().'): '
                .mb_substr($response->body(), 0, 500)
            );
        }

        return [$url, $requestId];
    }

    /**
     * Membatalkan invoice yang belum dibayar (Cancel Order API).
     *
     * Hanya untuk Transfer Bank dan QRIS, dan fiturnya harus dinyalakan di
     * DOKU Back Office (Order Cancellation). Di luar itu DOKU menolak, dan
     * pemanggil cukup menganggapnya gagal tanpa menghentikan pembatalan lokal.
     */
    public static function cancel(string $invoice, string $originalRequestId, string $note): bool
    {
        $clientId = (string) config('services.doku.client_id');
        $secret = (string) config('services.doku.secret_key');
        $target = '/checkout/v3/cancellations';

        $body = json_encode([
            'order' => ['invoice_number' => $invoice],
            'payment' => ['original_request_id' => $originalRequestId],
            'note' => $note,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $requestId = (string) \Illuminate\Support\Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        $response = Http::withHeaders([
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => self::signature($clientId, $requestId, $timestamp, $target, $body, $secret),
        ])
            ->withBody($body, 'application/json')
            ->timeout(15)
            ->post(self::baseUrl().$target);

        if (! $response->successful()) {
            \Illuminate\Support\Facades\Log::warning('DOKU cancel rejected', [
                'invoice' => $invoice,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
        }

        return $response->successful();
    }

    /**
     * Menanyakan status invoice langsung ke DOKU (Check Status API).
     * Cadangan bila notifikasi belum/tidak sampai.
     *
     * @return array{status: string, amount: mixed}|null null bila belum ada statusnya
     */
    public static function fetchStatus(string $invoice): ?array
    {
        $clientId = (string) config('services.doku.client_id');
        $secret = (string) config('services.doku.secret_key');

        $target = '/orders/v1/status/'.rawurlencode($invoice);
        $requestId = (string) \Illuminate\Support\Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        $response = Http::withHeaders([
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => self::signature($clientId, $requestId, $timestamp, $target, null, $secret),
        ])
            ->timeout(15)
            ->get(self::baseUrl().$target);

        if (! $response->successful()) {
            return null;
        }

        $status = $response->json('transaction.status') ?? $response->json('response.transaction.status');

        if (! is_string($status) || $status === '') {
            return null;
        }

        return [
            'status' => strtoupper($status),
            'amount' => $response->json('order.amount') ?? $response->json('response.order.amount'),
        ];
    }

    /**
     * Memeriksa tanda tangan notifikasi dari DOKU terhadap body mentahnya.
     */
    public static function verifyNotification(
        string $body,
        ?string $clientId,
        ?string $requestId,
        ?string $timestamp,
        ?string $signature,
        string $target,
    ): bool {
        $secret = (string) config('services.doku.secret_key');

        if (
            $secret === ''
            || ! is_string($clientId) || ! is_string($requestId)
            || ! is_string($timestamp) || ! is_string($signature)
            || ! hash_equals((string) config('services.doku.client_id'), $clientId)
        ) {
            return false;
        }

        return hash_equals(
            self::signature($clientId, $requestId, $timestamp, $target, $body, $secret),
            $signature
        );
    }
}
