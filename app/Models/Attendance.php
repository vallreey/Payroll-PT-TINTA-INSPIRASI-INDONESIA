<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'presence',
        'photo_path',
        'check_out_photo_path',
        'latitude',
        'longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_at',
        'check_out_at',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    // work_date sengaja TANPA cast 'date': ia bagian dari kunci unik (employee_id, work_date) dan harus
    // tersimpan persis 'Y-m-d'. Cast 'date' menyimpan 'Y-m-d 00:00:00' di SQLite sehingga pencarian
    // dengan 'Y-m-d' tidak cocok. Baca sebagai string, atau pakai Carbon::parse($attendance->work_date).
    protected static function booted(): void
    {
        // Jaga invarian: satu baris = satu hari kerja (unique employee_id + work_date).
        static::creating(function (self $attendance) {
            $attendance->work_date ??= $attendance->check_in_at?->toDateString();
        });
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    // ================= Relasi =================

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ================= Scope =================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
