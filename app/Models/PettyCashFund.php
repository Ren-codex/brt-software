<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashFund extends Model
{
    protected $fillable = ['name', 'gl_code', 'balance', 'weekly_budget', 'low_balance_threshold', 'is_active', 'created_by_id'];

    protected $casts = ['is_active' => 'boolean', 'balance' => 'float', 'weekly_budget' => 'float', 'low_balance_threshold' => 'float'];

    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class, 'fund_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
