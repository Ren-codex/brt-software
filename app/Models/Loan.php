<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'employee_id',
        'loan_type',
        'amount',
        'interest_rate',
        'term_months',
        'status',
        'purpose',
        'added_by_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function added_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
