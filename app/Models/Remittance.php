<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Remittance extends Model
{
    protected $fillable = [
        'remittance_no',
        'remittance_date',
        'summary',
        'total_amount',
        'status_id',
        'created_by_id',
        'approved_by_id',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'summary' => 'array',
        'approved_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'remittance_id');
    }
}
