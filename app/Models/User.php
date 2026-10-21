<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'profile_photo_path',
        'password',
        'role',
        'status',
        'is_active',
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
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ================= Relasi =================

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    // Role yang berhak melakukan approval (HRD, Supervisor, Direktur, Super Admin)
    public function isApprover(): bool
    {
        return in_array($this->role, ['hrd', 'supervisor', 'direktur', 'super_admin']);
    }
}
