<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DUMMY (kartu "Total job" di dashboard). Tidak dinamai `jobs` karena itu tabel queue Laravel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_pekerjaan', function (Blueprint $table) {
            $table->id('id_pekerjaan');
            $table->foreignId('id_layanan')->nullable()->constrained('tbl_layanan', 'id_layanan')->nullOnDelete();
            $table->foreignId('id_karyawan')->nullable()->constrained('tbl_karyawan', 'id_karyawan')->nullOnDelete();
            $table->string('judul');
            $table->enum('status', ['menunggu', 'berjalan', 'selesai'])->default('menunggu');
            $table->timestamps();

            $table->index('status', 'idx_pekerjaan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pekerjaan');
    }
};
