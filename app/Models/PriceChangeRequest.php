<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceChangeRequest extends Model
{
    protected $fillable = [
        'inventory_stocks_id',
        'product_id',
        'retail_price',
        'wholesale_price',
        'previous_retail_price',
        'previous_wholesale_price',
        'reason',
        'status_id',
        'requested_by_id',
        'approved_by_id',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'previous_retail_price' => 'decimal:2',
        'previous_wholesale_price' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function inventoryStock()
    {
        return $this->belongsTo(InventoryStocks::class, 'inventory_stocks_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
