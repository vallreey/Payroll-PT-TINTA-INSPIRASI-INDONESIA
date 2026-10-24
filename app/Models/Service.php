<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** DUMMY dashboard: tbl_layanan */
class Service extends Model
{
    use HasFactory;

    protected $table = 'tbl_layanan';

    protected $primaryKey = 'id_layanan';

    protected $fillable = ['nama_layanan', 'deskripsi', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function jobOrders()
    {
        return $this->hasMany(JobOrder::class, 'id_layanan', 'id_layanan');
    }
}
