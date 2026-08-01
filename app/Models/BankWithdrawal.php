<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankWithdrawal extends Model
{
    protected $fillable = [
        'withdrawal_no',
        'bank_account_id',
        'cash_account_id',
        'amount',
        'withdrawal_date',
        'reference',
        'notes',
        'created_by_id',
    ];

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
