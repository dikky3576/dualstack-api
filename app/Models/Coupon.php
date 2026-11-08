<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'usage_limit',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Kode selalu huruf besar tanpa spasi di tepi. */
    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Alasan kupon tidak bisa dipakai sekarang, atau null bila bisa.
     */
    public function unavailableReason(): ?string
    {
        if (! $this->is_active) {
            return 'Kupon ini tidak aktif.';
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return 'Kupon ini belum berlaku.';
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return 'Kupon ini sudah kedaluwarsa.';
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'Kuota kupon ini sudah habis.';
        }

        return null;
    }

    public function minPurchaseCents(): int
    {
        return (int) round((float) $this->min_purchase * 100);
    }

    /**
     * Potongan dalam sen untuk nilai belanja $baseCents (juga dalam sen).
     * Dibulatkan ke bawah ke Rupiah penuh karena Midtrans memakai bilangan
     * bulat, dan tidak pernah melebihi nilai belanja.
     */
    public function discountCents(int $baseCents): int
    {
        if ($this->type === self::TYPE_PERCENT) {
            $cents = (int) floor($baseCents * (float) $this->value / 100);

            if ($this->max_discount !== null) {
                $cents = min($cents, (int) round((float) $this->max_discount * 100));
            }
        } else {
            $cents = (int) round((float) $this->value * 100);
        }

        $cents = min($cents, $baseCents);

        return max(0, intdiv($cents, 100) * 100);
    }
}
