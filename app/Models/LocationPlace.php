<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationPlace extends Model
{
    protected $table = 'tbl_lokasi_kustom';

    protected $primaryKey = 'id_lokasi';

    protected $fillable = [
        'nama_lokasi',
        'keterangan',
        'latitude',
        'longitude',
        'id_pembuat',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_pembuat', 'id_pengguna');
    }
}
