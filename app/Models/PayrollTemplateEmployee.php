<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollTemplateEmployee extends Model
{
    protected $fillable = [
        'payroll_template_id',
        'employee_id'
    ];

    public function payroll_template(): BelongsTo
    {
        return $this->belongsTo(PayrollTemplate::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
