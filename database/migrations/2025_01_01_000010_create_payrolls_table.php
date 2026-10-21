<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_penggajian. bpjs_deduction = TOTAL BPJS (= bpjs_tk_deduction + bpjs_kes_deduction), diisi PayrollService.
 * Status memakai alur lengkap SRS (draft -> pending_approval -> approved/rejected -> paid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->decimal('basic_salary', 14, 2)->default(0);          // Gaji Pokok
            $table->decimal('allowance', 14, 2)->default(0);             // Tunjangan (transport, makan, dll)
            $table->decimal('attendance_deduction', 14, 2)->default(0);  // Potongan absensi
            $table->decimal('bpjs_deduction', 14, 2)->default(0);        // Iuran BPJS (total)
            $table->decimal('bpjs_tk_deduction', 14, 2)->default(0);     // BPJS Ketenagakerjaan
            $table->decimal('bpjs_kes_deduction', 14, 2)->default(0);    // BPJS Kesehatan
            $table->decimal('pph21_deduction', 14, 2)->default(0);       // Potongan PPh 21
            $table->decimal('other_deduction', 14, 2)->default(0);       // Potongan lain (kasbon, dll)
            $table->decimal('gross_salary', 14, 2)->default(0);          // Gaji Kotor
            $table->decimal('net_salary', 14, 2)->default(0);            // Gaji Bersih
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'paid'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('slip_path')->nullable(); // slip gaji (PDF)
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_period_id']);
            $table->index('status', 'idx_payroll_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
