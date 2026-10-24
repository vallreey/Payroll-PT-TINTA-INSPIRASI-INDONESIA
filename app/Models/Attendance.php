<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_absensi (satu baris per karyawan per hari) */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'tbl_absensi';

    protected $primaryKey = 'id_absensi';

    protected $fillable = [
        'id_karyawan',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'foto_masuk',
        'foto_keluar',
        'lat_masuk',
        'long_masuk',
        'lat_keluar',
        'long_keluar',
        'status',
        'status_persetujuan',
        'approved_by',
        'approved_at',
        'keterangan',
    ];

    // `tanggal` sengaja TANPA cast 'date': ia bagian dari kunci unik (id_karyawan, tanggal) dan harus tersimpan
    // persis 'Y-m-d'. Cast 'date' menyimpan 'Y-m-d 00:00:00' di SQLite sehingga pencarian 'Y-m-d' tidak cocok.
    // jam_masuk / jam_keluar bertipe TIME (string 'H:i:s').
    protected static function booted(): void
    {
        static::creating(function (self $attendance) {
            $attendance->tanggal ??= now()->toDateString();
        });
    }

    protected function casts(): array
    {
        return [
            'lat_masuk' => 'decimal:7',
            'long_masuk' => 'decimal:7',
            'lat_keluar' => 'decimal:7',
            'long_keluar' => 'decimal:7',
            'approved_at' => 'datetime',
        ];
    }

    // ================= Relasi =================

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_karyawan', 'id_karyawan');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id_pengguna');
    }

    // ================= Scope =================

    public function scopePending($query)
    {
        return $query->where('status_persetujuan', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status_persetujuan', 'approved');
    }
}
