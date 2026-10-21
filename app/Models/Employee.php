<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'supervisor_id',
        'nik',
        'position',
        'phone',
        'address',
        'gender',
        'birth_date',
        'join_date',
        'employment_status',
        'base_salary',
        'is_active',
        'photo_path',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'is_active' => 'boolean',
            'base_salary' => 'decimal:2',
        ];
    }

    // ================= Relasi =================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Atasan langsung (self-referencing)
    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    // Anak buah / karyawan yang disupervisi
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function locationTrackings()
    {
        return $this->hasMany(LocationTracking::class);
    }

    public function leaveQuotas()
    {
        return $this->hasMany(LeaveQuota::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    // ================= Accessor =================

    // URL foto profil (disk public; jalankan `php artisan storage:link`). Null jika belum ada foto.
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? asset('storage/'.ltrim($this->photo_path, '/')) : null);
    }

    // ================= Helper =================

    public function leaveQuotaFor(int $leaveTypeId, int $year)
    {
        return $this->leaveQuotas()
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
    }
}
