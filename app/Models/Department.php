<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_departemen */
class Department extends Model
{
    use HasFactory;

    protected $table = 'tbl_departemen';

    protected $primaryKey = 'id_departemen';

    protected $fillable = [
        'nama_departemen',
        'deskripsi',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'id_departemen', 'id_departemen');
    }
}
