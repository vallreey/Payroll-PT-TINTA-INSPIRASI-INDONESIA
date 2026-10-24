<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_notifikasi (hanya created_at) */
class Notifikasi extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'tbl_notifikasi';

    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'id_pengguna',
        'judul',
        'pesan',
        'tipe',
        'is_read',
    ];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id_pengguna');
    }

    public function scopeBelumDibaca($query)
    {
        return $query->where('is_read', false);
    }
}
