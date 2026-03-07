<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_id',
        'reason',
        'status_id',
        'created_by_id',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function items()
    {
        return $this->hasMany(StockReturnItem::class, 'stock_return_id');
    }

    public function logs()
    {
        return $this->hasMany(StockReturnLog::class, 'stock_return_id');
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
