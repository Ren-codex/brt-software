<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransfer extends Model
{
    protected $fillable = [
        'transfer_no', 'transfer_date', 'from_bank_account_id', 'to_bank_account_id',
        'amount', 'reference_number', 'notes', 'created_by_id',
    ];

    public function fromBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id');
    }

    public function toBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
