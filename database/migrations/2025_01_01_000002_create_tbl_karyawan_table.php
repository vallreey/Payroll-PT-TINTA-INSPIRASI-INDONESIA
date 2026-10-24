<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_karyawan.
 * - status: aktif/nonaktif. Status "cuti" pada SDD sengaja tidak disimpan; diturunkan dari tbl_izin_cuti
 *   yang disetujui dan mencakup hari ini (agar tidak ada data basi).
 * - sisa_cuti = sisa jatah cuti tahunan (SDD), direset oleh scheduler tahunan.
 * - Kolom di luar SDD: id_atasan (alur approval), jenis_kelamin, tanggal_lahir, status_kepegawaian, data rekening.
 * - Foto karyawan memakai tbl_pengguna.foto_profil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_karyawan', function (Blueprint $table) {
            $table->id('id_karyawan');
            $table->foreignId('id_pengguna')->unique()->constrained('tbl_pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->foreignId('id_departemen')->nullable()->constrained('tbl_departemen', 'id_departemen')->nullOnDelete();
            $table->foreignId('id_atasan')->nullable()->constrained('tbl_karyawan', 'id_karyawan')->nullOnDelete();
            $table->string('nik', 20)->unique();
            $table->string('nama_lengkap', 150);
            $table->string('jabatan', 100)->nullable();
            $table->date('tanggal_masuk')->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('status_kepegawaian', ['tetap', 'kontrak', 'lapangan'])->default('tetap');
            $table->decimal('gaji_pokok', 15, 2)->default(0);      // gaji pokok per bulan
            $table->unsignedSmallInteger('sisa_cuti')->default(12); // sisa jatah cuti tahunan
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->string('dokumen_ktp')->nullable();              // path scan KTP
            // Untuk transfer gaji otomatis (definisi Payroll di SRS)
            $table->string('nama_bank', 100)->nullable();
            $table->string('no_rekening', 50)->nullable();
            $table->string('nama_pemilik_rekening', 150)->nullable();
            $table->timestamps();

            $table->index('status', 'idx_karyawan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_karyawan');
    }
};
