<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Produk digital tidak dikirim secara fisik, jadi pesanan yang sudah
     * lunas harus berstatus "completed". Merapikan pesanan lama yang
     * terlanjur tertinggal di "pending" / "processing".
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_status', 'paid')
            ->whereIn('status', ['pending', 'processing'])
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        // Perubahan data satu arah; status lama tidak dapat dipulihkan.
    }
};
