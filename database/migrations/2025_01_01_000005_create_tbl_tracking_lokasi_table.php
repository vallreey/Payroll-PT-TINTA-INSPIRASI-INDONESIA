<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SDD tbl_tracking_lokasi. Hanya created_at (jejak lokasi tidak pernah diubah).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_tracking_lokasi', function (Blueprint $table) {
            $table->id('id_tracking');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('waktu');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_karyawan', 'tanggal', 'waktu'], 'idx_tracking_karyawan_waktu');
            $table->index(['tanggal', 'waktu'], 'idx_tracking_waktu'); // peta semua karyawan pada satu waktu
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_tracking_lokasi');
    }
};
