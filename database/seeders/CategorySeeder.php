<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Website Templates',
                'slug' => 'website-templates',
                'description' => 'Template website modern untuk berbagai kebutuhan.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Admin Dashboard',
                'slug' => 'admin-dashboard',
                'description' => 'Template dashboard admin dan panel manajemen.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'UI Kits',
                'slug' => 'ui-kits',
                'description' => 'Komponen UI dan desain antarmuka siap pakai.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mobile App Templates',
                'slug' => 'mobile-app-templates',
                'description' => 'Template aplikasi mobile untuk berbagai platform.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'E-commerce',
                'slug' => 'ecommerce',
                'description' => 'Template toko online dan marketplace.',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Free Templates',
                'slug' => 'free-templates',
                'description' => 'Koleksi template gratis untuk dicoba.',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}