<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'quota',
        'used',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    // ================= Helper =================

    public function remaining(): int
    {
        return max($this->quota - $this->used, 0);
    }

    public function hasEnoughQuota(int $days): bool
    {
        return $this->remaining() >= $days;
    }
}
