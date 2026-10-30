<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'category' => 'website-templates',
                'name' => 'Nexus SaaS Landing Page',
                'slug' => 'nexus-saas-landing-page',
                'short_description' => 'Landing page SaaS modern dengan desain premium dan responsif.',
                'description' => 'Template landing page untuk startup SaaS, produk digital, dan layanan teknologi.',
                'price' => 149000,
                'discount_price' => 99000,
                'technology' => 'React, Tailwind CSS',
                'is_free' => false,
                'is_featured' => true,
            ],
            [
                'category' => 'admin-dashboard',
                'name' => 'Obsidian Admin Dashboard',
                'slug' => 'obsidian-admin-dashboard',
                'short_description' => 'Dashboard gelap futuristik dengan tata letak analitik.',
                'description' => 'Template dashboard untuk mengelola data, pengguna, dan aktivitas bisnis.',
                'price' => 199000,
                'discount_price' => null,
                'technology' => 'React, TypeScript, Tailwind CSS',
                'is_free' => false,
                'is_featured' => true,
            ],
            [
                'category' => 'ui-kits',
                'name' => 'Prism UI Kit',
                'slug' => 'prism-ui-kit',
                'short_description' => 'Kumpulan komponen antarmuka untuk produk digital modern.',
                'description' => 'UI kit untuk mempercepat proses desain halaman website dan aplikasi.',
                'price' => 79000,
                'discount_price' => null,
                'technology' => 'Figma',
                'is_free' => false,
                'is_featured' => false,
            ],
            [
                'category' => 'mobile-app-templates',
                'name' => 'Orbit Mobile Starter',
                'slug' => 'orbit-mobile-starter',
                'short_description' => 'Template awal aplikasi mobile dengan UI bersih.',
                'description' => 'Starter template untuk membangun aplikasi mobile dengan navigasi dan komponen dasar.',
                'price' => 129000,
                'discount_price' => null,
                'technology' => 'React Native, Expo',
                'is_free' => false,
                'is_featured' => true,
            ],
            [
                'category' => 'ecommerce',
                'name' => 'Commerce Starter Kit',
                'slug' => 'commerce-starter-kit',
                'short_description' => 'Fondasi antarmuka toko online yang mudah dikembangkan.',
                'description' => 'Template e-commerce dengan halaman katalog, detail produk, dan keranjang sebagai fondasi pengembangan.',
                'price' => 0,
                'discount_price' => null,
                'technology' => 'React, Tailwind CSS',
                'is_free' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($products as $item) {
            $category = Category::where('slug', $item['category'])->firstOrFail();

            unset($item['category']);

            Product::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, [
                    'category_id' => $category->id,
                    'status' => 'published',
                    'sales_count' => 0,
                    'average_rating' => 0,
                    'thumbnail' => null,
                    'preview_images' => [],
                    'demo_url' => null,
                    'file_path' => null,
                    'file_name' => null,
                    'file_size' => null,
                ])
            );
        }
    }
}