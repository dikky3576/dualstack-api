<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Template gratis tidak punya pesanan. Unduhan yang tercatat di sini
        // menjadi bukti bahwa pelanggan memakai template tersebut, sehingga
        // ia boleh memberi ulasan.
        Schema::create('product_downloads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        // Ulasan template gratis tidak terikat ke pesanan.
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_downloads');

        // order_id sengaja dibiarkan nullable: mengembalikannya menjadi
        // NOT NULL akan gagal bila sudah ada ulasan template gratis.
    }
};
