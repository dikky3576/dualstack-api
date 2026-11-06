<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDownloadController extends Controller
{
    /**
     * Template gratis yang pernah diunduh pelanggan.
     */
    public function index(Request $request): JsonResponse
    {
        $downloads = ProductDownload::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('product', function ($query) {
                $query->where('status', 'published')
                    ->where('is_free', true);
            })
            ->with('product:id,name,slug')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (ProductDownload $download) => [
                'product_name' => $download->product->name,
                'product_slug' => $download->product->slug,
                'downloaded_at' => $download->created_at,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Daftar template gratis yang diunduh berhasil diambil.',
            'data' => $downloads,
        ]);
    }
}
