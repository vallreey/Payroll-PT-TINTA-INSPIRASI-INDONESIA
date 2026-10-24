<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SDD tbl_notifikasi (diisi oleh NotificationService). Hanya created_at.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->foreignId('id_pengguna')->constrained('tbl_pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->string('judul', 200);
            $table->text('pesan');
            $table->enum('tipe', ['izin', 'cuti', 'absensi', 'gaji']);
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_pengguna', 'is_read'], 'idx_notifikasi_pengguna_baca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_notifikasi');
    }
};
