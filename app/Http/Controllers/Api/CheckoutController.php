<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CouponResolver;
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
        // Membeli mewajibkan email yang sudah terverifikasi.
        if (! $request->user()->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Verifikasi emailmu dulu sebelum membeli. Cek kotak masuk, atau kirim ulang email verifikasi dari banner di atas.',
                'code' => 'email_not_verified',
            ], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $priced = $this->priceItems($validated['items']);

            $subtotalCents = $priced['subtotal_cents'];
            $discountCents = $priced['discount_cents'];
            $orderItems = $priced['items'];

            $couponCents = 0;
            $coupon = null;

            if (! empty($validated['coupon_code'])) {
                [$coupon, $couponCents] = $this->resolveCoupon(
                    $validated['coupon_code'],
                    $request->user(),
                    $subtotalCents - $discountCents,
                    true
                );
            }

            $totalCents = $subtotalCents - $discountCents - $couponCents;
            $isFreeOrder = $totalCents === 0;

            $order = Order::create([
                'order_number' => 'DS-' .
                    Str::upper(Str::random(12)),
                'user_id' => $request->user()->id,
                'subtotal' => $subtotalCents / 100,
                'discount_amount' => $discountCents / 100,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'coupon_discount' => $couponCents / 100,
                'total_amount' => $totalCents / 100,
                'currency' => 'IDR',
                'status' => $isFreeOrder ? 'completed' : 'pending',
                'payment_status' => $isFreeOrder ? 'paid' : 'pending',
                'paid_at' => $isFreeOrder ? now() : null,
            ]);

            // Kuota dipesan saat pesanan dibuat dan dikembalikan bila
            // pesanan dibatalkan atau kedaluwarsa.
            if ($coupon) {
                $coupon->increment('used_count');
            }

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

    /**
     * Pratinjau potongan kupon untuk isi keranjang, tanpa membuat pesanan.
     */
    public function couponPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $priced = $this->priceItems($validated['items'], false);
        $base = $priced['subtotal_cents'] - $priced['discount_cents'];

        [$coupon, $couponCents] = $this->resolveCoupon(
            $validated['code'],
            $request->user(),
            $base,
            false
        );

        return response()->json([
            'success' => true,
            'message' => 'Kupon berhasil dipakai.',
            'data' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'discount' => $couponCents / 100,
                'total' => ($base - $couponCents) / 100,
            ],
        ]);
    }

    /**
     * Menghitung harga semua produk dalam sen.
     *
     * @return array{subtotal_cents:int,discount_cents:int,items:array<int,array>}
     */
    private function priceItems(array $items, bool $lock = true): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = Product::query()
            ->whereIn('id', $productIds)
            ->where('status', 'published')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $products = $query->get()->keyBy('id');

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

        return [
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'items' => $orderItems,
        ];
    }

    /**
     * Memvalidasi kupon dan menghitung potongannya.
     *
     * @return array{0:Coupon,1:int} kupon dan potongan dalam sen
     */
    private function resolveCoupon(
        string $code,
        User $user,
        int $baseCents,
        bool $lock
    ): array {
        return CouponResolver::resolve($code, $user, $baseCents, $lock);
    }
}
