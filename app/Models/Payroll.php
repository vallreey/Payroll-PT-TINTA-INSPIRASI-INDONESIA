<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_penggajian */
class Payroll extends Model
{
    use HasFactory;

    protected $table = 'tbl_penggajian';

    protected $primaryKey = 'id_penggajian';

    protected $fillable = [
        'id_karyawan',
        'bulan',
        'tahun',
        'gaji_pokok',
        'tunjangan',
        'potongan_absensi',
        'pph21',
        'bpjs_tk',
        'bpjs_kes',
        'gaji_bersih',
        'status',
        'tanggal_bayar',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'gaji_pokok' => 'decimal:2',
            'tunjangan' => 'decimal:2',
            'potongan_absensi' => 'decimal:2',
            'pph21' => 'decimal:2',
            'bpjs_tk' => 'decimal:2',
            'bpjs_kes' => 'decimal:2',
            'gaji_bersih' => 'decimal:2',
            'tanggal_bayar' => 'date',
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

    // ================= Helper =================

    // Gaji kotor = gaji pokok + tunjangan (tidak disimpan)
    public function gajiKotor(): float
    {
        return round((float) $this->gaji_pokok + (float) $this->tunjangan, 2);
    }

    /**
     * Hitung ulang gaji_bersih dari komponen yang ada. Panggil sebelum save() jika komponen berubah.
     * Rumus: gaji pokok + tunjangan - (potongan absensi + PPh21 + BPJS TK + BPJS Kesehatan) (SDD AC-009).
     */
    public function recalculate(): void
    {
        $potongan = (float) $this->potongan_absensi + (float) $this->pph21
            + (float) $this->bpjs_tk + (float) $this->bpjs_kes;

        $this->gaji_bersih = round($this->gajiKotor() - $potongan, 2);
    }

    // ================= Scope =================

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }
}
