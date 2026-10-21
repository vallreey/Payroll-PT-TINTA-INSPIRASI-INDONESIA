<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pengganti kolom SDD karyawan.sisa_cuti: kuota per karyawan, per jenis cuti, per tahun. Sisa = quota - used.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('quota');
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_quotas');
    }
};
