<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DokuClient;
use App\Support\DokuSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        Log::info('DOKU webhook received.', [
            'path' => $request->path(),
            'has_signature' => $request->hasHeader('Signature'),
        ]);

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

        $data = $request->json()->all();
        $invoice = data_get($data, 'order.invoice_number');
        $amount = data_get($data, 'order.amount');
        $status = strtoupper((string) data_get($data, 'transaction.status'));

        if (! is_string($invoice) || $invoice === '' || ! is_numeric($amount) || $status === '') {
            return response()->json(['success' => false, 'message' => 'Notifikasi tidak lengkap.'], 400);
        }

        $result = DokuSettlement::apply($invoice, $amount, $status);

        if ($result['order'] === null) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($result['order'] === false) {
            Log::warning('DOKU webhook: amount mismatch.', ['invoice' => $invoice]);

            return response()->json(['success' => false, 'message' => 'Nominal pembayaran tidak cocok.'], 422);
        }

        if ($result['paid']) {
            DokuSettlement::sendPaidEmail($result['order']->id);
        }

        return response()->json(['success' => true, 'message' => 'Notifikasi pembayaran berhasil diproses.']);
    }
}
