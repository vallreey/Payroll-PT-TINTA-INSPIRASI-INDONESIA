<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel: tbl_dokumen_karyawan */
class EmployeeDocument extends Model
{
    use HasFactory;

    protected $table = 'tbl_dokumen_karyawan';

    protected $primaryKey = 'id_dokumen';

    protected $fillable = [
        'id_karyawan',
        'jenis_dokumen',
        'path_file',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_karyawan', 'id_karyawan');
    }
}
