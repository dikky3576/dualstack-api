<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'thumbnail',
        'preview_images',
        'demo_url',
        'price',
        'discount_price',
        'file_path',
        'file_name',
        'file_size',
        'technology',
        'is_free',
        'is_featured',
        'status',
        'sales_count',
        'average_rating',
    ];

    protected function casts(): array
    {
        return [
            'preview_images' => 'array',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'is_free' => 'boolean',
            'is_featured' => 'boolean',
            'file_size' => 'integer',
            'sales_count' => 'integer',
            'average_rating' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Apakah produk ini pernah masuk ke pesanan (order_items menyimpan
     * product_id dengan restrictOnDelete).
     */
    public function hasOrders(): bool
    {
        return OrderItem::query()->where('product_id', $this->id)->exists();
    }

    /**
     * Menghapus produk.
     *
     * - Belum pernah dipesan: baris dihapus permanen beserta ulasan, wishlist,
     *   catatan unduhan, dan file gambar/ZIP-nya.
     * - Pernah dipesan: tetap soft delete, karena riwayat pesanan masih
     *   merujuk ke baris ini.
     *
     * @return bool true bila datanya benar-benar sudah hilang dari database.
     */
    public function purge(): bool
    {
        if ($this->hasOrders()) {
            if (! $this->trashed()) {
                $this->delete();
            }

            return false;
        }

        $thumbnail = $this->thumbnail;
        $previews = $this->preview_images ?? [];
        $archive = $this->file_path;

        DB::transaction(function () {
            // reviews.product_id memakai restrictOnDelete; wishlists dan
            // product_downloads ikut terhapus lewat cascade.
            Review::query()->where('product_id', $this->id)->delete();

            $this->forceDelete();
        });

        // File dihapus setelah baris database berhasil dihapus.
        $publicDisk = Storage::disk('public');

        foreach (array_filter([$thumbnail, ...$previews]) as $path) {
            if (
                is_string($path) &&
                (
                    str_starts_with($path, 'products/thumbnails/') ||
                    str_starts_with($path, 'products/previews/')
                )
            ) {
                $publicDisk->delete($path);
            }
        }

        if (
            is_string($archive) &&
            $archive !== '' &&
            basename($archive) === $archive
        ) {
            Storage::disk('template_downloads')->delete($archive);
        }

        return true;
    }
}