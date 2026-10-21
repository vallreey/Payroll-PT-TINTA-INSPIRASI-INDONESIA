<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SDD tbl_karyawan. Nama lengkap ada di users.name; sisa cuti di leave_quotas; scan KTP di employee_documents.
// Status "cuti" tidak disimpan: diturunkan dari leave_requests yang disetujui.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            // Atasan langsung, dipakai untuk alur approval (HRD/Supervisor/CEO)
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('nik')->unique();
            $table->string('position')->nullable(); // Jabatan
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->date('birth_date')->nullable();
            $table->date('join_date')->nullable();
            $table->enum('employment_status', ['tetap', 'kontrak', 'lapangan'])->default('tetap');
            $table->decimal('base_salary', 15, 2)->default(0); // gaji pokok per bulan
            $table->boolean('is_active')->default(true);        // karyawan aktif/nonaktif (resign)
            $table->string('photo_path')->nullable();
            // Untuk transfer gaji otomatis (definisi Payroll di SRS)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->timestamps();

            $table->index('is_active', 'idx_employees_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
