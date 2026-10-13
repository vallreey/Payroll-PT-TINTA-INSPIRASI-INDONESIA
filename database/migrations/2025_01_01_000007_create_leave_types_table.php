<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Cuti Tahunan, Izin, Sakit, dll
            $table->unsignedInteger('default_quota')->default(0); // 0 = tanpa kuota (mis. Sakit)
            $table->boolean('requires_attachment')->default(false); // surat dokter, dll
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
