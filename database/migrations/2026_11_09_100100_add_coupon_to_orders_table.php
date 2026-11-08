<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('discount_amount')
                ->constrained('coupons')
                ->nullOnDelete();

            // Salinan kode dan potongan saat dipakai, supaya riwayat pesanan
            // tetap benar walau kuponnya kelak diubah.
            $table->string('coupon_code', 50)->nullable()->after('coupon_id');
            $table->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'coupon_discount']);
        });
    }
};
