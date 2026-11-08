<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // false = akun dibuat lewat Google dan pemiliknya belum menetapkan
        // password (kolom password berisi nilai acak yang tidak diketahui).
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_set')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set');
        });
    }
};
