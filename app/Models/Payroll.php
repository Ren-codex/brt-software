<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'payroll_no',
        'pay_period_start',
        'pay_period_end',
        'total_amount',
        'status',
        'payroll_template_id',
        'created_by',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function template()
    {
        return $this->belongsTo(PayrollTemplate::class, 'payroll_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
