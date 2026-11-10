<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackController extends Controller
{
    /**
     * Mencatat satu kunjungan halaman. Selalu menjawab 204: pencatatan tidak
     * boleh mengganggu pengunjung, dan kegagalannya tidak perlu diketahui.
     */
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:site,product'],
            'product_id' => ['required_if:type,product', 'nullable', 'integer', 'min:1'],
        ]);

        $productId = 0;

        if ($data['type'] === 'product') {
            $productId = (int) $data['product_id'];

            // Hanya produk terbit yang dihitung.
            $exists = Product::query()
                ->whereKey($productId)
                ->where('status', 'published')
                ->exists();

            if (! $exists) {
                return response()->noContent();
            }
        }

        try {
            DB::table('daily_views')->upsert(
                [[
                    'date' => now('Asia/Jakarta')->toDateString(),
                    'type' => $data['type'],
                    'product_id' => $productId,
                    'views' => 1,
                ]],
                ['date', 'type', 'product_id'],
                ['views' => DB::raw('views + 1')]
            );
        } catch (\Throwable $e) {
            Log::warning('Track view failed', ['message' => $e->getMessage()]);
        }

        return response()->noContent();
    }
}
