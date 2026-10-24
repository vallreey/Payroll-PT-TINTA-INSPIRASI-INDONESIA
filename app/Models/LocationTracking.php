<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_tracking_lokasi (hanya created_at) */
class LocationTracking extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'tbl_tracking_lokasi';

    protected $primaryKey = 'id_tracking';

    protected $fillable = [
        'id_karyawan',
        'tanggal',
        'waktu',
        'latitude',
        'longitude',
        'keterangan',
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
