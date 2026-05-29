<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'fund_id',
        'replenishment_request_id',
        'expense_type',
        'amount',
        'expense_date',
        'description',
        'receipt_path',
        'status',
        'added_by_id',
    ];

    public function added_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function status_info(): BelongsTo
    {
        return $this->belongsTo(ListStatus::class, 'status', 'slug');
    }

    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function replenishment_request()
    {
        return $this->belongsTo(ReplenishmentRequest::class, 'replenishment_request_id');
    }
}
