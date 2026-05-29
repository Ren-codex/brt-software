<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseBudget extends Model
{
    protected $fillable = [
        'expense_type',
        'month',
        'year',
        'amount',
        'created_by_id',
    ];

    protected $casts = ['amount' => 'float', 'month' => 'integer', 'year' => 'integer'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
