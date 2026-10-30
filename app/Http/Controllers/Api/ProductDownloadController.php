<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductDownloadController extends Controller
{
    public function download(Request $request, string $id)
    {
        // Hanya produk yang dipublikasikan yang dapat diunduh.
        $product = Product::query()
            ->where('status', 'published')
            ->findOrFail($id);

        $user = $request->user();

        // Template gratis boleh diunduh oleh pelanggan yang login.
        $canDownload = (bool) $product->is_free;

        // Template berbayar harus memiliki pesanan terverifikasi
        // milik pengguna yang sedang login.
        if (! $canDownload) {
            $canDownload = OrderItem::query()
                ->where('product_id', $product->id)
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->where('payment_status', 'paid')
                        ->whereIn('status', ['processing', 'completed']);
                })
                ->exists();
        }

        if (! $canDownload) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu belum memiliki akses unduhan untuk template ini.',
            ], 403);
        }

        // Untuk keamanan, file_path hanya boleh berupa nama file,
        // bukan path absolut atau path yang mengarah ke direktori lain.
        $storedFile = $product->file_path;

        if (
            ! is_string($storedFile) ||
            $storedFile === '' ||
            basename($storedFile) !== $storedFile ||
            str_contains($storedFile, '..') ||
            ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $storedFile)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'File template belum tersedia.',
            ], 404);
        }

        $disk = Storage::disk('template_downloads');

        if (! $disk->exists($storedFile)) {
            return response()->json([
                'success' => false,
                'message' => 'File template belum tersedia di penyimpanan.',
            ], 404);
        }

        // Nama unduhan tidak boleh mengandung path atau karakter
        // yang tidak diharapkan.
        $fileName = basename(
            $product->file_name ?: $storedFile
        );

        $fileName = preg_replace(
            '/[^A-Za-z0-9._ -]/',
            '_',
            $fileName
        );

        return $disk->download(
            $storedFile,
            $fileName ?: $storedFile,
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
