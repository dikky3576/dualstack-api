<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Request-Id yang dipakai saat membuat invoice DOKU. DOKU
            // memintanya kembali (original_request_id) untuk membatalkan invoice.
            $table->string('payment_request_id', 128)->nullable()->after('payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_request_id');
        });
    }
};
