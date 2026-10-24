<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DUMMY (kartu Schedule di dashboard) + peserta jadwal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_jadwal', function (Blueprint $table) {
            $table->id('id_jadwal');
            $table->string('judul');
            $table->enum('tipe', ['meeting', 'event'])->default('meeting');
            $table->dateTime('waktu_mulai')->index();
            $table->dateTime('waktu_selesai');
            $table->string('lokasi')->nullable();      // mis. "Google Meet"
            $table->string('url_meeting')->nullable(); // wajib http(s) saat input
            $table->foreignId('dibuat_oleh')->nullable()->constrained('tbl_pengguna', 'id_pengguna')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tbl_peserta_jadwal', function (Blueprint $table) {
            $table->foreignId('id_jadwal')->constrained('tbl_jadwal', 'id_jadwal')->cascadeOnDelete();
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->primary(['id_jadwal', 'id_karyawan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_peserta_jadwal');
        Schema::dropIfExists('tbl_jadwal');
    }
};
