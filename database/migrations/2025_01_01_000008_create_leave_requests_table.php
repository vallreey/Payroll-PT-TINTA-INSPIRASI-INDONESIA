<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_izin_cuti.
 * total_days = jumlah hari yang memotong kuota (menggantikan trigger trg_kurangi_cuti; dihitung di Service).
 * approval_note = catatan approver untuk setuju maupun tolak (AC-008); rejection_reason khusus alasan penolakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days')->default(1);
            $table->text('reason');
            $table->string('attachment_path')->nullable(); // surat sakit, dsb (opsional)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status'], 'idx_leave_employee_status');
            $table->index(['status', 'start_date', 'end_date'], 'idx_leave_status_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
