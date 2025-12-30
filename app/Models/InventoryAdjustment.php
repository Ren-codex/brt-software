<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'inventory_stocks_id',
        'new_quantity',
        'previous_quantity',
        'reason',
        'adjustment_date',
        'adjusted_by_id',
        'type',
    ];

    public function inventoryStock()
    {
        return $this->belongsTo(InventoryStocks::class, 'inventory_stocks_id');
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by_id');
    }
}
