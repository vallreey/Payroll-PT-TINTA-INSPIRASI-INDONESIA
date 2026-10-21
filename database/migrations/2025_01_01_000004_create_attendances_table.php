<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_absensi. Satu baris = satu karyawan per hari; check-out meng-update baris yang sama.
 *
 *   photo_path / latitude / longitude  = data CHECK-IN   (SDD: foto_masuk, lat_masuk, long_masuk)
 *   check_out_*                        = data CHECK-OUT  (SDD: foto_keluar, lat_keluar, long_keluar)
 *   status                             = status PERSETUJUAN absensi (pending/approved/rejected)
 *   presence                           = hasil kehadiran (hadir/terlambat/alpha), snapshot saat dicatat
 *
 * work_date disimpan sebagai string 'Y-m-d' (tanpa cast 'date' di model) karena bagian dari kunci unik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->enum('presence', ['hadir', 'terlambat', 'alpha'])->default('hadir');
            $table->string('photo_path'); // bukti foto kehadiran saat check-in (wajib, FR-007)
            $table->string('check_out_photo_path')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'uq_attendance_employee_day');
            $table->index(['employee_id', 'check_in_at'], 'idx_absensi_tgl'); // SDD: idx_absensi_tgl
            $table->index('work_date', 'idx_attendance_work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
