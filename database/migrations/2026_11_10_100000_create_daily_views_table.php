<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hitungan per hari saja: tidak menyimpan IP, user agent, atau akun.
        Schema::create('daily_views', function (Blueprint $table) {
            $table->id();
            $table->date('date');

            // site: semua halaman publik. product: halaman detail satu produk.
            $table->string('type', 10);

            // 0 untuk type=site (bukan NULL agar unique index bekerja di MySQL).
            $table->unsignedBigInteger('product_id')->default(0);

            $table->unsignedInteger('views')->default(0);

            $table->unique(['date', 'type', 'product_id']);
            $table->index(['type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_views');
    }
};
