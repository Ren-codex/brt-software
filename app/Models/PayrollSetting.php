<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $fillable = [
        'field_name',
        'formula',
        'value',
        'is_active'
    ];

    public function logs()
    {
        return $this->hasMany(PayrollSettingLog::class, 'payroll_setting_id');
    }
}
