<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DUMMY (tab Task di dashboard).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_tugas', function (Blueprint $table) {
            $table->id('id_tugas');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->string('judul');
            $table->date('tenggat')->nullable();
            $table->enum('status', ['terbuka', 'selesai'])->default('terbuka');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_tugas');
    }
};
