<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BankDeposit extends Model
{
    public const TYPE_CASH = 'cash';
    public const TYPE_CHECK = 'check';

    public const STATUS_PENDING = 'pending';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'deposit_no',
        'cash_account_id',
        'bank_account_id',
        'deposit_type',
        'amount',
        'deposit_date',
        'check_date',
        'check_number',
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

    /**
     * The date the money actually reaches the bank: a check only becomes good
     * funds on its own date, so that is the date its journal entry carries.
     */
    public function effectiveDate(): string
    {
        return $this->isCheck() && $this->check_date
            ? $this->check_date->toDateString()
            : (string) $this->deposit_date;
    }

    public function isCheck(): bool
    {
        return $this->deposit_type === self::TYPE_CHECK;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Cash is good the moment it is deposited; a check only on its own date.
     */
    public function isDueForPosting($asOf = null): bool
    {
        if (!$this->isCheck() || !$this->check_date) {
            return true;
        }

        return $this->check_date->startOfDay()
            ->lte(Carbon::parse($asOf ?: now())->startOfDay());
    }

    public function scopeDueForPosting($query, $asOf)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('deposit_type', self::TYPE_CHECK)
            ->whereNotNull('check_date')
            ->whereDate('check_date', '<=', $asOf);
    }

    public function cashAccount()
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function remittances()
    {
        return $this->hasMany(Remittance::class, 'bank_deposit_id');
    }
}
