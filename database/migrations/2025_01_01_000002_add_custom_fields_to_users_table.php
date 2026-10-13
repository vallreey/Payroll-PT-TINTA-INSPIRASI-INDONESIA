<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 5 role sesuai SRS: super_admin, hrd, supervisor, direktur, karyawan
            $table->enum('role', ['super_admin', 'hrd', 'supervisor', 'direktur', 'karyawan'])
                  ->default('karyawan')
                  ->after('email');

            // Registrasi karyawan butuh persetujuan Super Admin (FR-001, use case 001)
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('role');

            $table->foreignId('approved_by')->nullable()->after('status')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['role', 'status', 'approved_at']);
        });
    }
};
