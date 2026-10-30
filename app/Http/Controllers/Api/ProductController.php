<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'technology' => ['nullable', 'string', 'max:100'],
            'is_free' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'min_rating' => ['sometimes', 'numeric', 'between:0,5'],
            'sort' => [
                'sometimes',
                Rule::in([
                    'latest',
                    'price_asc',
                    'price_desc',
                    'popular',
                    'rating',
                ]),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ]);

        // Validasi batas harga.
        if (
            isset($filters['min_price'], $filters['max_price']) &&
            $filters['min_price'] > $filters['max_price']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Harga minimum tidak boleh lebih besar dari harga maksimum.',
            ], 422);
        }

        $query = Product::query()
            ->where('status', 'published')
            ->with([
                'category:id,name,slug',
            ])
            ->withCount('reviews');

        // Pencarian nama dan deskripsi produk.
        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter kategori berdasarkan slug.
        if (! empty($filters['category'])) {
            $query->whereHas('category', function ($q) use ($filters) {
                $q->where('slug', $filters['category'])
                    ->where('is_active', true);
            });
        }

        // Filter kategori berdasarkan ID.
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter teknologi.
        if (! empty($filters['technology'])) {
            $query->where(
                'technology',
                'like',
                '%' . $filters['technology'] . '%'
            );
        }

        // Filter produk gratis atau berbayar.
        if (array_key_exists('is_free', $filters)) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        // Filter produk unggulan.
        if (array_key_exists('is_featured', $filters)) {
            $query->where(
                'is_featured',
                $request->boolean('is_featured')
            );
        }

        // Filter harga.
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Filter rating minimum.
        if (isset($filters['min_rating'])) {
            $query->where(
                'average_rating',
                '>=',
                $filters['min_rating']
            );
        }

        // Sorting berdasarkan pilihan pengguna.
        $sortOptions = [
            'latest' => ['created_at', 'desc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'popular' => ['sales_count', 'desc'],
            'rating' => ['average_rating', 'desc'],
        ];

        $sort = $filters['sort'] ?? 'latest';

        [$column, $direction] = $sortOptions[$sort];

        $products = $query
            ->orderBy($column, $direction)
            ->orderBy('id', 'desc')
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();

        $products->through(function (Product $product) {
            return $this->appendMediaUrls($product);
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk berhasil diambil.',
            'filters' => $filters,
            'data' => $products,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with('category:id,name,slug')
            ->withCount('reviews')
            ->firstOrFail();

        $product = $this->appendMediaUrls($product);

        return response()->json([
            'success' => true,
            'message' => 'Detail produk berhasil diambil.',
            'data' => $product,
        ]);
    }

    private function appendMediaUrls(Product $product): Product
    {
        $disk = Storage::disk('public');

        $product->setAttribute(
            'thumbnail_url',
            $product->thumbnail
                ? $disk->url($product->thumbnail)
                : null
        );

        $product->setAttribute(
            'preview_image_urls',
            collect($product->preview_images ?? [])
                ->map(fn ($path) => $disk->url($path))
                ->values()
        );

        // Jangan pernah menampilkan lokasi file ZIP privat.
        $product->makeHidden([
            'file_path',
            'file_name',
            'file_size',
        ]);

        return $product;
    }
}