<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_penggajian. Status diperluas dari SDD (draft/approved/paid) dengan pending_approval dan rejected
 * untuk alur persetujuan SRS (use case 017/030/043/056). Gaji kotor tidak disimpan (= gaji_pokok + tunjangan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_penggajian', function (Blueprint $table) {
            $table->id('id_penggajian');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');   // 1-12
            $table->unsignedSmallInteger('tahun');
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);        // transport, makan, dll
            $table->decimal('potongan_absensi', 15, 2)->default(0);
            $table->decimal('pph21', 15, 2)->default(0);
            $table->decimal('bpjs_tk', 15, 2)->default(0);
            $table->decimal('bpjs_kes', 15, 2)->default(0);
            $table->decimal('gaji_bersih', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'paid'])->default('draft');
            $table->date('tanggal_bayar')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('tbl_pengguna', 'id_pengguna')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['id_karyawan', 'bulan', 'tahun'], 'uq_penggajian_karyawan_periode');
            $table->index('status', 'idx_penggajian_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_penggajian');
    }
};
