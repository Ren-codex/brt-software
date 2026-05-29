<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReplenishmentRequest extends Model
{
    protected $fillable = [
        'reference_no',
        'fund_id',
        'period_label',
        'total_amount',
        'expense_count',
        'status',
        'submitted_at',
        'reviewed_by_id',
        'reviewed_at',
        'review_notes',
        'created_by_id',
    ];

    protected $casts = [
        'total_amount'  => 'float',
        'submitted_at'  => 'datetime',
        'reviewed_at'   => 'datetime',
    ];

    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'replenishment_request_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
