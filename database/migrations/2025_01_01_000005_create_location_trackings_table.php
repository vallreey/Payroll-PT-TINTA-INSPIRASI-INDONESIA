<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SDD tbl_tracking_lokasi (tanggal + waktu = recorded_at; keterangan = note)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->dateTime('recorded_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'recorded_at']);
            $table->index('recorded_at', 'idx_tracking_recorded_at'); // peta semua karyawan pada satu waktu
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_trackings');
    }
};
