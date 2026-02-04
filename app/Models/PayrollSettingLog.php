<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSettingLog extends Model
{
    protected $fillable = [
        'changed_data',
        'updated_by_id',
        'payroll_setting_id',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function payrollSetting()
    {
        return $this->belongsTo(PayrollSetting::class, 'payroll_setting_id');
    }
}
