<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penyimpanan dokumen digital karyawan selain KTP (SRS 3.2: Penyimpanan Dokumen Digital)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_dokumen_karyawan', function (Blueprint $table) {
            $table->id('id_dokumen');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->string('jenis_dokumen'); // Ijazah, Kontrak Kerja, dll
            $table->string('path_file');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_dokumen_karyawan');
    }
};
