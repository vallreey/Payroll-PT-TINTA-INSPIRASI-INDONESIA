<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** DUMMY dashboard: tbl_pekerjaan */
class JobOrder extends Model
{
    use HasFactory;

    protected $table = 'tbl_pekerjaan';

    protected $primaryKey = 'id_pekerjaan';

    protected $fillable = ['id_layanan', 'id_karyawan', 'judul', 'status'];

    public function service()
    {
        return $this->belongsTo(Service::class, 'id_layanan', 'id_layanan');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_karyawan', 'id_karyawan');
    }
}
