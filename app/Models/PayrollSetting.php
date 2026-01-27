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
}
