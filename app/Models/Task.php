<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** DUMMY dashboard: tbl_tugas */
class Task extends Model
{
    use HasFactory;

    protected $table = 'tbl_tugas';

    protected $primaryKey = 'id_tugas';

    protected $fillable = ['id_karyawan', 'judul', 'tenggat', 'status'];

    protected function casts(): array
    {
        return ['tenggat' => 'date'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_karyawan', 'id_karyawan');
    }
}
