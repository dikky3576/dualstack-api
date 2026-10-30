<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductReviewController extends Controller
{
    /**
     * Menampilkan review publik suatu produk.
     */
    public function index(string $productId): JsonResponse
    {
        $product = Product::query()
            ->where('status', 'published')
            ->findOrFail($productId);

        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        $reviews->through(function (Review $review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
                'user' => [
                    'name' => $review->user->name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Review produk berhasil diambil.',
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'average_rating' => $product->average_rating,
                ],
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * Pelanggan membuat review produk.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'rating' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $user = $request->user();

        $product = Product::query()
            ->where('status', 'published')
            ->findOrFail($validated['product_id']);

        // Pastikan pelanggan belum pernah mengulas produk.
        if (
            Review::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu sudah memberikan review untuk produk ini.',
            ], 409);
        }

        // Cari transaksi yang benar-benar sudah dibayar.
        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->whereIn('status', ['processing', 'completed'])
            ->whereHas('items', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->latest('paid_at')
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu harus membeli dan menyelesaikan pembayaran produk sebelum memberikan review.',
            ], 403);
        }

        $review = DB::transaction(function () use (
            $user,
            $product,
            $order,
            $validated
        ) {
            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'order_id' => $order->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);

            $this->refreshAverageRating($product->id);

            return $review;
        });

        return response()->json([
            'success' => true,
            'message' => 'Review berhasil ditambahkan.',
            'data' => $review->load('user:id,name'),
            'average_rating' => Product::find($product->id)?->average_rating,
        ], 201);
    }

    /**
     * Pelanggan memperbarui review miliknya.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => [
                'sometimes',
                'required',
                'integer',
                'between:1,5',
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data review yang diperbarui.',
            ], 422);
        }

        $review = Review::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        DB::transaction(function () use ($review, $validated) {
            $review->update($validated);

            $this->refreshAverageRating($review->product_id);
        });

        return response()->json([
            'success' => true,
            'message' => 'Review berhasil diperbarui.',
            'data' => $review->fresh(),
            'average_rating' => Product::find($review->product_id)?->average_rating,
        ]);
    }

    /**
     * Pelanggan menghapus review miliknya.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $review = Review::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        DB::transaction(function () use ($review) {
            $productId = $review->product_id;

            $review->delete();

            $this->refreshAverageRating($productId);
        });

        return response()->json([
            'success' => true,
            'message' => 'Review berhasil dihapus.',
        ]);
    }

    /**
     * Menghitung ulang rating rata-rata produk.
     */
    private function refreshAverageRating(int $productId): void
    {
        $average = Review::where('product_id', $productId)->avg('rating');

        Product::whereKey($productId)->update([
            'average_rating' => round((float) ($average ?? 0), 2),
        ]);
    }
}
