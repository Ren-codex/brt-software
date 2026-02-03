<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollTemplate extends Model
{
    protected $fillable = [
        'name',
        'created_by_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function payroll_template_employees(): HasMany
    {
        return $this->hasMany(PayrollTemplateEmployee::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'payroll_template_employees');
    }
}
