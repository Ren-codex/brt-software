<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'pay_period_start',
        'pay_period_end',
        'status'
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
