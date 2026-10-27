<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Tabel SDD: tbl_pengguna */
class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'tbl_pengguna';

    protected $primaryKey = 'id_pengguna';

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'status',
        'status_persetujuan',
        'foto_profil',
        'approved_by',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ================= Relasi =================

    public function employee()
    {
        return $this->hasOne(Employee::class, 'id_pengguna', 'id_pengguna');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id_pengguna');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'id_pengguna', 'id_pengguna');
    }

    // ================= Accessor =================

    // Nama lengkap ada di tbl_karyawan (SDD). Akun "Admin murni" tanpa data karyawan memakai username.
    protected function namaTampil(): Attribute
    {
        return Attribute::get(fn () => $this->employee?->nama_lengkap ?? $this->username ?? $this->email);
    }

    // ================= Helper Role =================

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isHrd(): bool
    {
        return $this->role === 'hrd';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isDirektur(): bool
    {
        return $this->role === 'direktur';
    }

    public function isKaryawan(): bool
    {
        return $this->role === 'karyawan';
    }

    // Registrasi sudah disetujui Super Admin
    public function isApproved(): bool
    {
        return $this->status_persetujuan === 'approved';
    }

    // Akun tidak dinonaktifkan
    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }

    // Role yang berhak melakukan approval (HRD, Supervisor, Direktur, Super Admin)
    public function isApprover(): bool
    {
        return in_array($this->role, ['hrd', 'supervisor', 'direktur', 'super_admin']);
    }
}
