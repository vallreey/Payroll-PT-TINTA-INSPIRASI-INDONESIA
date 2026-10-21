<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sumber: SDD tbl_pengguna + SRS v2.0 (5 role, registrasi butuh persetujuan Super Admin).
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // nama lengkap
            $table->string('username', 100)->nullable()->unique();   // NOT NULL setelah login/registrasi pindah ke username
            $table->string('email')->unique();
            $table->string('profile_photo_path')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // 5 role sesuai SRS: super_admin, hrd, supervisor, direktur, karyawan
            $table->enum('role', ['super_admin', 'hrd', 'supervisor', 'direktur', 'karyawan'])->default('karyawan');
            // Status PERSETUJUAN registrasi (FR-001, use case 001/061)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Akun aktif/nonaktif (Super Admin dapat menonaktifkan akun, AC-003)
            $table->boolean('is_active')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role', 'idx_users_role');
            $table->index('status', 'idx_users_status');
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
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
