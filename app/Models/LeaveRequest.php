<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_izin_cuti */
class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'tbl_izin_cuti';

    protected $primaryKey = 'id_izin';

    protected $fillable = [
        'id_karyawan',
        'jenis',
        'sub_jenis',
        'tanggal_mulai',
        'tanggal_selesai',
        'total_hari',
        'alasan',
        'dokumen',
        'status',
        'approved_by',
        'approved_at',
        'catatan_approval',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'total_hari' => 'integer',
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
        return $query->where('status', 'pending');
    }

    // ================= Helper =================

    // Jumlah hari kalender inklusif (dasar awal total_hari; aturan hari libur ditangani Service)
    public function durationInDays(): int
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }
}
