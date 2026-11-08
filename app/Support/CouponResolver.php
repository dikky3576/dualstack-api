<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponResolver
{
    /**
     * Memvalidasi kupon dan menghitung potongannya.
     *
     * @param  int  $baseCents  nilai belanja setelah diskon produk, dalam sen
     * @param  int|null  $ignoreOrderId  pesanan yang sedang diubah (tidak dihitung sebagai pemakaian lama)
     * @return array{0:Coupon,1:int} kupon dan potongan dalam sen
     */
    public static function resolve(
        string $code,
        User $user,
        int $baseCents,
        bool $lock = false,
        ?int $ignoreOrderId = null
    ): array {
        $query = Coupon::where('code', Coupon::normalize($code));

        if ($lock) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();

        if (! $coupon) {
            self::fail('Kode kupon tidak ditemukan.');
        }

        if ($reason = $coupon->unavailableReason()) {
            self::fail($reason);
        }

        if ($baseCents <= 0) {
            self::fail('Kupon tidak berlaku untuk pesanan gratis.');
        }

        if ($baseCents < $coupon->minPurchaseCents()) {
            $min = number_format((float) $coupon->min_purchase, 0, ',', '.');
            self::fail("Minimal belanja untuk kupon ini Rp{$min}.");
        }

        // Satu kupon hanya sekali per akun; pesanan yang batal tidak dihitung.
        $alreadyUsed = Order::where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->where('status', '!=', 'cancelled')
            ->when($ignoreOrderId, fn ($q) => $q->where('id', '!=', $ignoreOrderId))
            ->exists();

        if ($alreadyUsed) {
            self::fail('Kamu sudah pernah memakai kupon ini.');
        }

        $discount = $coupon->discountCents($baseCents);

        if ($discount <= 0) {
            self::fail('Kupon ini tidak memberi potongan untuk pesananmu.');
        }

        return [$coupon, $discount];
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'coupon_code' => [$message],
        ]);
    }
}
