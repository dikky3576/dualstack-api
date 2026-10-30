<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * Membuat pesanan pelanggan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $productIds = collect($validated['items'])
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('status', 'published')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Satu atau lebih produk tidak tersedia untuk dibeli.',
                    ],
                ]);
            }

            $subtotalCents = 0;
            $discountCents = 0;
            $orderItems = [];

            foreach ($productIds as $productId) {
                $product = $products->get($productId);

                // Semua perhitungan memakai satuan sen agar
                // tidak bergantung pada pembulatan float.
                $baseCents = $product->is_free
                    ? 0
                    : (int) round((float) $product->price * 100);

                $effectiveCents = $baseCents;

                if (
                    ! $product->is_free &&
                    $product->discount_price !== null
                ) {
                    $saleCents = (int) round(
                        (float) $product->discount_price * 100
                    );

                    if ($saleCents < $baseCents) {
                        $effectiveCents = $saleCents;
                    }
                }

                $itemDiscountCents = $baseCents - $effectiveCents;

                $subtotalCents += $baseCents;
                $discountCents += $itemDiscountCents;

                $orderItems[] = [
                    'product' => $product,
                    'base_cents' => $baseCents,
                    'discount_cents' => $itemDiscountCents,
                    'total_cents' => $effectiveCents,
                ];
            }

            $totalCents = $subtotalCents - $discountCents;
            $isFreeOrder = $totalCents === 0;

            $order = Order::create([
                'order_number' => 'DS-' .
                    Str::upper(Str::random(12)),
                'user_id' => $request->user()->id,
                'subtotal' => $subtotalCents / 100,
                'discount_amount' => $discountCents / 100,
                'total_amount' => $totalCents / 100,
                'currency' => 'IDR',
                'status' => $isFreeOrder ? 'completed' : 'pending',
                'payment_status' => $isFreeOrder ? 'paid' : 'pending',
                'paid_at' => $isFreeOrder ? now() : null,
            ]);

            foreach ($orderItems as $item) {
                $product = $item['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'quantity' => 1,
                    'unit_price' => $item['base_cents'] / 100,
                    'discount_amount' => $item['discount_cents'] / 100,
                    'line_total' => $item['total_cents'] / 100,
                ]);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $order->load('items'),
        ], 201);
    }
}
