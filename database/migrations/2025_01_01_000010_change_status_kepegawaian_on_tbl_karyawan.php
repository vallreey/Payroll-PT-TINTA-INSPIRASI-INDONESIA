<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis kepegawaian mengikuti prototype halaman Karyawan: Permanent / Contract / Probation.
 * Sebelumnya: tetap, kontrak, lapangan ("lapangan" bukan jenis kepegawaian, melainkan jenis pekerjaan).
 * Data lama "lapangan" dipindahkan ke "kontrak" sebelum nilai enum diganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pindahkan nilai yang akan dihapus agar perubahan enum tidak gagal di MySQL (strict mode).
        DB::table('tbl_karyawan')->where('status_kepegawaian', 'lapangan')->update(['status_kepegawaian' => 'kontrak']);

        Schema::table('tbl_karyawan', function (Blueprint $table) {
            $table->enum('status_kepegawaian', ['tetap', 'kontrak', 'percobaan'])->default('tetap')->change();
        });
    }

    public function down(): void
    {
        // Kembalikan ke enum lama; "percobaan" tidak ada di enum lama, jadi dipetakan ke "kontrak".
        DB::table('tbl_karyawan')->where('status_kepegawaian', 'percobaan')->update(['status_kepegawaian' => 'kontrak']);

        Schema::table('tbl_karyawan', function (Blueprint $table) {
            $table->enum('status_kepegawaian', ['tetap', 'kontrak', 'lapangan'])->default('tetap')->change();
        });
    }
};
