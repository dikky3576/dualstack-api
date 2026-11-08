<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'subtotal',
        'discount_amount',
        'coupon_id',
        'coupon_code',
        'coupon_discount',
        'total_amount',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Mengembalikan satu kuota kupon saat pesanan batal atau kedaluwarsa.
     * Catatan kupon di pesanan tetap disimpan sebagai riwayat.
     */
    public function releaseCoupon(): void
    {
        if (! $this->coupon_id) {
            return;
        }

        Coupon::whereKey($this->coupon_id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');
    }

    /**
     * Memakai satu kuota lagi, mis. pesanan yang sudah dibatalkan ternyata
     * dibayar dari tab lama.
     */
    public function consumeCoupon(): void
    {
        if (! $this->coupon_id) {
            return;
        }

        Coupon::whereKey($this->coupon_id)->increment('used_count');
    }
}