<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_tracking_lokasi', function (Blueprint $table) {
            $table->boolean('is_simulated')->default(false)->after('keterangan');
        });

        Schema::create('tbl_lokasi_kustom', function (Blueprint $table) {
            $table->id('id_lokasi');
            $table->string('nama_lokasi', 120);
            $table->string('keterangan', 500)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->foreignId('id_pembuat')->constrained('tbl_pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['latitude', 'longitude'], 'idx_lokasi_kustom_koordinat');
        });

        Schema::create('tbl_penugasan_lokasi', function (Blueprint $table) {
            $table->id('id_penugasan');
            $table->foreignId('id_karyawan')->unique()->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->string('nama_lokasi', 120);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->foreignId('id_pemberi_tugas')->constrained('tbl_pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_penugasan_lokasi');
        Schema::dropIfExists('tbl_lokasi_kustom');
        Schema::table('tbl_tracking_lokasi', function (Blueprint $table) {
            $table->dropColumn('is_simulated');
        });
    }
};
