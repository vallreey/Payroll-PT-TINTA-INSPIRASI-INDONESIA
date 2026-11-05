<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_penugasan_lokasi_tujuan', function (Blueprint $table) {
            $table->id('id_tujuan');
            $table->foreignId('id_karyawan')->constrained('tbl_karyawan', 'id_karyawan')->cascadeOnDelete();
            $table->string('nama_lokasi', 120);
            $table->string('keterangan', 500)->nullable();
            $table->string('ikon', 30)->default('pin');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->foreignId('id_pemberi_tugas')->constrained('tbl_pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['id_karyawan', 'id_tujuan'], 'idx_tujuan_karyawan');
        });

        DB::table('tbl_penugasan_lokasi')->orderBy('id_penugasan')->get()->each(function ($assignment): void {
            DB::table('tbl_penugasan_lokasi_tujuan')->insert([
                'id_karyawan' => $assignment->id_karyawan,
                'nama_lokasi' => $assignment->nama_lokasi,
                'latitude' => $assignment->latitude,
                'longitude' => $assignment->longitude,
                'id_pemberi_tugas' => $assignment->id_pemberi_tugas,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_penugasan_lokasi_tujuan');
    }
};
