<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStocks extends Model
{
    protected $fillable = [
        'received_item_id',
        'quantity',
        'retail_price',
        'wholesale_price',
        'expiration_date',
    ];

    public function receivedItem()
    {
        return $this->belongsTo(ReceivedItem::class);
    }

    public function inventoryAdjustments()
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
