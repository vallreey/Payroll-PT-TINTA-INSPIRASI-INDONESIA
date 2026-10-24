<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SDD tbl_departemen
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_departemen', function (Blueprint $table) {
            $table->id('id_departemen');
            $table->string('nama_departemen', 100)->unique();
            $table->text('deskripsi')->nullable(); // deskripsi tugas departemen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_departemen');
    }
};
