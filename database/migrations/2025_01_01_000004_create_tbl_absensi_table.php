<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_absensi. Satu baris = satu karyawan per hari; check-out meng-update baris yang sama.
 *   status             = hasil kehadiran (hadir/terlambat/alpha), snapshot saat dicatat   [SDD]
 *   status_persetujuan = persetujuan atasan (pending/approved/rejected)                  [SRS: Persetujuan Absensi]
 * `tanggal` disimpan sebagai string 'Y-m-d' (model tanpa cast 'date') karena bagian dari kunci unik.
 * Unique (id_karyawan, tanggal) sekaligus menjadi idx_absensi_tgl pada SDD 2.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_absensi', function (Blueprint $table) {
            $table->id('id_absensi');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_masuk');
            $table->time('jam_keluar')->nullable();
            $table->string('foto_masuk');                 // bukti foto kehadiran (wajib, FR-007)
            $table->string('foto_keluar')->nullable();
            $table->decimal('lat_masuk', 10, 7);
            $table->decimal('long_masuk', 10, 7);
            $table->decimal('lat_keluar', 10, 7)->nullable();
            $table->decimal('long_keluar', 10, 7)->nullable();
            $table->enum('status', ['hadir', 'terlambat', 'alpha'])->default('hadir');
            $table->enum('status_persetujuan', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('tbl_pengguna', 'id_pengguna')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['id_karyawan', 'tanggal'], 'idx_absensi_tgl');
            $table->index('tanggal', 'idx_absensi_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_absensi');
    }
};
