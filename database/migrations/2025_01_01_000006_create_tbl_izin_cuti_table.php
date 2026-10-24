<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_izin_cuti. Kolom tambahan: sub_jenis (mis. "Cuti Melahirkan"), total_hari (dasar pemotongan
 * tbl_karyawan.sisa_cuti, menggantikan trigger trg_kurangi_cuti), approved_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_izin_cuti', function (Blueprint $table) {
            $table->id('id_izin');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->enum('jenis', ['izin', 'cuti', 'sakit']);
            $table->string('sub_jenis', 100)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedSmallInteger('total_hari')->default(1);
            $table->text('alasan');
            $table->string('dokumen')->nullable(); // surat dokter, dsb
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('tbl_pengguna', 'id_pengguna')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('catatan_approval')->nullable(); // catatan approver untuk setuju MAUPUN tolak (AC-008)
            $table->timestamps();

            $table->index(['id_karyawan', 'status'], 'idx_izin_karyawan_status');
            $table->index(['status', 'tanggal_mulai', 'tanggal_selesai'], 'idx_izin_status_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_izin_cuti');
    }
};
