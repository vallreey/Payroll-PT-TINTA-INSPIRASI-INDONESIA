<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationAssignment extends Model
{
    protected $table = 'tbl_penugasan_lokasi_tujuan';

    protected $primaryKey = 'id_tujuan';

    protected $fillable = [
        'id_karyawan',
        'nama_lokasi',
        'keterangan',
        'ikon',
        'latitude',
        'longitude',
        'id_pemberi_tugas',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_karyawan', 'id_karyawan');
    }
}
