<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD tbl_notifikasi memakai tabel `notifications` BAWAAN Laravel (skema sama dengan
 * `php artisan make:notifications-table`), bukan tabel buatan sendiri, supaya
 * $user->notify(), unreadNotifications, dan markAsRead() langsung bekerja.
 *
 * Pemetaan: id_pengguna -> notifiable_id (+ notifiable_type = App\Models\User),
 * is_read -> read_at IS NOT NULL, judul/pesan/tipe -> JSON di kolom `data`
 * (tipe: izin | cuti | absensi | gaji), created_at -> created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
