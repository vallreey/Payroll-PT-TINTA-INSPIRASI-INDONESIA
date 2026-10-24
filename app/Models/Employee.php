<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tabel SDD: tbl_karyawan */
class Employee extends Model
{
    use HasFactory;

    protected $table = 'tbl_karyawan';

    protected $primaryKey = 'id_karyawan';

    protected $fillable = [
        'id_pengguna',
        'id_departemen',
        'id_atasan',
        'nik',
        'nama_lengkap',
        'jabatan',
        'tanggal_masuk',
        'no_telepon',
        'alamat',
        'jenis_kelamin',
        'tanggal_lahir',
        'status_kepegawaian',
        'gaji_pokok',
        'sisa_cuti',
        'status',
        'dokumen_ktp',
        'nama_bank',
        'no_rekening',
        'nama_pemilik_rekening',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'tanggal_masuk' => 'date',
            'gaji_pokok' => 'decimal:2',
            'sisa_cuti' => 'integer',
        ];
    }

    // ================= Relasi =================

    public function user()
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id_pengguna');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'id_departemen', 'id_departemen');
    }

    // Atasan langsung (self-referencing)
    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'id_atasan', 'id_karyawan');
    }

    // Anak buah / karyawan yang disupervisi
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'id_atasan', 'id_karyawan');
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'id_karyawan', 'id_karyawan');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'id_karyawan', 'id_karyawan');
    }

    public function locationTrackings()
    {
        return $this->hasMany(LocationTracking::class, 'id_karyawan', 'id_karyawan');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'id_karyawan', 'id_karyawan');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'id_karyawan', 'id_karyawan');
    }

    // ================= Accessor =================

    // URL foto profil (tbl_pengguna.foto_profil, disk public; jalankan `php artisan storage:link`).
    protected function photoUrl(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->user?->foto_profil;

            return $path ? asset('storage/'.ltrim($path, '/')) : null;
        });
    }
}
