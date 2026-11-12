<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnedProductController extends Controller
{
    /**
     * Slug template berbayar yang sudah dibeli pelanggan (pesanan lunas dan tidak
     * dibatalkan, aturan yang sama dengan izin unduh). Dipakai katalog untuk
     * mengganti tombol "Beli" menjadi tombol unduhan.
     */
    public function index(Request $request): JsonResponse
    {
        $slugs = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $request->user()->id)
            ->where('orders.payment_status', 'paid')
            ->whereIn('orders.status', ['processing', 'completed'])
            ->distinct()
            ->pluck('order_items.product_slug')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Template yang sudah dibeli berhasil diambil.',
            'data' => $slugs,
        ]);
    }
}
