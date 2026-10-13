<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'basic_salary',
        'allowance',
        'bpjs_deduction',
        'pph21_deduction',
        'other_deduction',
        'gross_salary',
        'net_salary',
        'status',
        'approved_by',
        'approved_at',
        'paid_at',
        'slip_path',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowance' => 'decimal:2',
            'bpjs_deduction' => 'decimal:2',
            'pph21_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    // ================= Relasi =================

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ================= Helper =================

    /**
     * Hitung ulang gaji kotor & bersih berdasarkan komponen yang ada.
     * Panggil sebelum save() jika basic_salary/allowance/deduction berubah.
     */
    public function recalculate(): void
    {
        $this->gross_salary = $this->basic_salary + $this->allowance;
        $totalDeduction = $this->bpjs_deduction + $this->pph21_deduction + $this->other_deduction;
        $this->net_salary = $this->gross_salary - $totalDeduction;
    }

    // ================= Scope =================

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }
}
