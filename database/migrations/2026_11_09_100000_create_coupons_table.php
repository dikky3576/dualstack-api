<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Selalu disimpan huruf besar.
            $table->string('code', 50)->unique();

            // percent: value = persen (1-100). fixed: value = nominal Rupiah.
            $table->string('type', 10);
            $table->decimal('value', 12, 2);

            // Minimal belanja (setelah diskon produk) agar kupon berlaku.
            $table->decimal('min_purchase', 12, 2)->default(0);
            // Batas potongan untuk kupon persen; kosong = tanpa batas.
            $table->decimal('max_discount', 12, 2)->nullable();

            // Kuota total pemakaian; kosong = tanpa batas.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
