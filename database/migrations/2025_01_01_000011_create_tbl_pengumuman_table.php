<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DUMMY (tab Announcement di dashboard).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_pengumuman', function (Blueprint $table) {
            $table->id('id_pengumuman');
            $table->string('judul');
            $table->text('isi');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('tbl_pengguna', 'id_pengguna')->nullOnDelete();
            $table->timestamp('tanggal_terbit')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pengumuman');
    }
};
