<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankWithdrawal extends Model
{
    public const METHOD_SLIP = 'slip';
    public const METHOD_CHECK = 'check';

    public const STATUS_PENDING = 'pending';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'withdrawal_no',
        'bank_account_id',
        'cash_account_id',
        'withdrawal_method',
        'amount',
        'withdrawal_date',
        'check_number',
        'check_date',
        'status',
        'posted_at',
        'reference',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'check_date' => 'date',
        'posted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_POSTED,
    ];

    public function isCheck(): bool
    {
        return $this->withdrawal_method === self::METHOD_CHECK;
    }

    /**
     * The day the money actually leaves the account: a check drawn to withdraw
     * cash empties the account when it is presented, not when it is written.
     */
    public function effectiveDate(): string
    {
        return $this->isCheck() && $this->check_date
            ? $this->check_date->toDateString()
            : (string) $this->withdrawal_date;
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function cashAccount()
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
