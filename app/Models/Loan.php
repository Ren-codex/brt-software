<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'loan_no',
        'employee_id',
        'loan_type',
        'amount',
        'interest_rate',
        'term_months',
        'status',
        'purpose',
        'added_by_id',
        'amtpaid',
        'remaining_balance',
        'remaining_term_to_pay',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function added_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LoanLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }
}
