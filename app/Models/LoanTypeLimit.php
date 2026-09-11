<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanTypeLimit extends Model
{
    protected $fillable = [
        'loan_type',
        'max_amount',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
    ];
}
