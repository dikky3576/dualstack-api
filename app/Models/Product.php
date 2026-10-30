<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}