<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'default_quota',
        'requires_attachment',
    ];

    protected function casts(): array
    {
        return [
            'requires_attachment' => 'boolean',
        ];
    }

    public function leaveQuotas()
    {
        return $this->hasMany(LeaveQuota::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
