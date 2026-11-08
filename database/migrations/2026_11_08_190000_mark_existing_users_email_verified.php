<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Akun yang sudah ada sebelum verifikasi email diwajibkan untuk checkout
     * dianggap terverifikasi, supaya tidak ada pelanggan lama yang terkunci.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Tidak ada yang dikembalikan: data lama tidak bisa dibedakan lagi.
    }
};
