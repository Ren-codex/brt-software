<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'payroll_no',
        'pay_period_start',
        'pay_period_end',
        'total_amount',
        'status_id',
        'payroll_template_id',
        'created_by',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'approved_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PayrollLog::class);
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }
}
