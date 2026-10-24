<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** DUMMY dashboard: tbl_pengumuman */
class Announcement extends Model
{
    use HasFactory;

    protected $table = 'tbl_pengumuman';

    protected $primaryKey = 'id_pengumuman';

    protected $fillable = ['judul', 'isi', 'dibuat_oleh', 'tanggal_terbit'];

    protected function casts(): array
    {
        return ['tanggal_terbit' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh', 'id_pengguna');
    }
}
