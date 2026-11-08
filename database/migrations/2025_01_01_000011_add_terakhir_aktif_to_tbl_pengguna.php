<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "Terakhir Aktif" pada halaman Manajemen Pengguna. Tidak ada di SDD; diisi middleware
 * EnsureAccountIsActive (paling sering sekali per 5 menit) sehingga ringan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pengguna', function (Blueprint $table) {
            $table->timestamp('terakhir_aktif')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pengguna', function (Blueprint $table) {
            $table->dropColumn('terakhir_aktif');
        });
    }
};
