<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    protected $fillable = [
        'bank_account_id', 'period_end', 'statement_balance',
        'notes', 'status', 'created_by_id',
    ];

    protected $casts = ['statement_balance' => 'float', 'period_end' => 'date'];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function clearedItems()
    {
        return $this->hasMany(BankReconciliationClear::class, 'reconciliation_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
