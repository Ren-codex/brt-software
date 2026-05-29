<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashTransaction extends Model
{
    protected $fillable = [
        'transaction_no', 'fund_id', 'type', 'amount', 'category',
        'description', 'transaction_date', 'reference_number',
        'receipt_path', 'source_type', 'bank_account_id', 'created_by_id',
    ];

    protected $casts = ['amount' => 'float'];

    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
