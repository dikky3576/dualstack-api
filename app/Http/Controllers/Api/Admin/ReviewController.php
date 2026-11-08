<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * Daftar seluruh ulasan untuk moderasi.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $reviews = Review::query()
            ->with([
                'user:id,name,email',
                // Produk yang sudah di-soft-delete tetap ditampilkan namanya.
                'product' => fn ($query) => $query->withTrashed()->select('id', 'name', 'slug'),
            ])
            ->when($request->filled('rating'), function ($query) use ($validated) {
                $query->where('rating', $validated['rating']);
            })
            ->when($request->filled('search'), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('product', function ($p) use ($search) {
                            $p->withTrashed()->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        $reviews->through(fn (Review $review) => [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at,
            'user' => $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
                'email' => $review->user->email,
            ] : null,
            'product' => $review->product ? [
                'id' => $review->product->id,
                'name' => $review->product->name,
                'slug' => $review->product->slug,
            ] : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar ulasan berhasil diambil.',
            'data' => $reviews,
        ]);
    }

    /**
     * Menghapus ulasan (moderasi) dan menghitung ulang rating produk.
     */
    public function destroy(string $id): JsonResponse
    {
        $review = Review::query()->findOrFail($id);

        DB::transaction(function () use ($review) {
            $productId = $review->product_id;

            $review->delete();

            $average = Review::where('product_id', $productId)->avg('rating');

            Product::withTrashed()->whereKey($productId)->update([
                'average_rating' => round((float) ($average ?? 0), 2),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Ulasan berhasil dihapus.',
        ]);
    }
}
