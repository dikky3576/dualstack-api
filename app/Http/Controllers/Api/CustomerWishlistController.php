<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerWishlistController extends Controller
{
    /**
     * Menampilkan wishlist milik pelanggan.
     */
    public function index(Request $request): JsonResponse
    {
        $wishlists = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('product', function ($query) {
                $query->where('status', 'published');
            })
            ->with([
                'product:id,category_id,name,slug,short_description,thumbnail,price,discount_price,is_free,is_featured,average_rating',
            ])
            ->latest()
            ->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Daftar wishlist berhasil diambil.',
            'data' => $wishlists,
        ]);
    }

    /**
     * Menambahkan produk ke wishlist.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
        ]);

        $product = Product::query()
            ->where('status', 'published')
            ->findOrFail($validated['product_id']);

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $wishlist->load([
            'product:id,category_id,name,slug,short_description,thumbnail,price,discount_price,is_free,is_featured,average_rating',
        ]);

        return response()->json([
            'success' => true,
            'message' => $wishlist->wasRecentlyCreated
                ? 'Produk berhasil ditambahkan ke wishlist.'
                : 'Produk sudah ada di wishlist.',
            'data' => $wishlist,
        ], $wishlist->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Menghapus produk dari wishlist.
     */
    public function destroy(Request $request, string $productId): JsonResponse
    {
        $wishlist = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if (! $wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan dalam wishlist.',
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus dari wishlist.',
        ]);
    }
}
