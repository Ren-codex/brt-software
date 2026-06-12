<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'voucher_no',
        'fund_id',
        'replenishment_request_id',
        'expense_type',
        'gl_account_id',
        'payment_method',
        'bank_account_id',
        'reference_no',
        'amount',
        'expense_date',
        'payee',
        'description',
        'receipt_path',
        'status',
        'added_by_id',
        'submitted_by_id',
        'approved_by_id',
        'released_by_id',
        'submitted_at',
        'approved_at',
        'released_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'released_at'  => 'datetime',
        'amount'       => 'float',
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

    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }
}
