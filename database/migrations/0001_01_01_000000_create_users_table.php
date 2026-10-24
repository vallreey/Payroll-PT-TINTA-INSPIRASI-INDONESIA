<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SDD tbl_pengguna. Nama lengkap TIDAK di sini, tetapi di tbl_karyawan.nama_lengkap (sesuai SDD);
        // akun "Admin murni" tanpa data karyawan ditampilkan memakai username.
        Schema::create('tbl_pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->string('username', 100)->nullable()->unique(); // NOT NULL setelah login/registrasi pindah ke username
            $table->string('email', 150)->unique();
            $table->string('password');
            // 5 role sesuai SRS v2.0: super_admin, hrd, supervisor, direktur, karyawan
            $table->enum('role', ['super_admin', 'hrd', 'supervisor', 'direktur', 'karyawan'])->default('karyawan');
            // SDD: status akun aktif/nonaktif (Super Admin dapat menonaktifkan akun, AC-003)
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            // Persetujuan registrasi oleh Super Admin (FR-001, use case 001/061, AC-002)
            $table->enum('status_persetujuan', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('foto_profil')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('tbl_pengguna', 'id_pengguna')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('email_verified_at')->nullable(); // kolom bawaan framework
            $table->rememberToken();                            // kolom bawaan framework
            $table->timestamps();

            $table->index('role', 'idx_pengguna_role');
            $table->index('status_persetujuan', 'idx_pengguna_persetujuan');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pengguna');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
