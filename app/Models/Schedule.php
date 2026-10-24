<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** DUMMY dashboard: tbl_jadwal (+ tbl_peserta_jadwal) */
class Schedule extends Model
{
    use HasFactory;

    protected $table = 'tbl_jadwal';

    protected $primaryKey = 'id_jadwal';

    protected $fillable = ['judul', 'tipe', 'waktu_mulai', 'waktu_selesai', 'lokasi', 'url_meeting', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh', 'id_pengguna');
    }

    public function attendees()
    {
        return $this->belongsToMany(Employee::class, 'tbl_peserta_jadwal', 'id_jadwal', 'id_karyawan', 'id_jadwal', 'id_karyawan');
    }
}
