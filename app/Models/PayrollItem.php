<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'basic_salary',
        'deductions',
        'earnings',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'total_days',
        'loans',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'loans' => 'array',
        'deductions' => 'array',
        'earnings' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Get the current overtime rate from PayrollSetting
    public function getOvertimeRate()
    {
        $setting = PayrollSetting::active()
            ->effectiveOn($this->payroll->pay_period_start ?? now())
            ->first();

        return $setting ? $setting->overtime_rate : 1.5;
    }
}
