<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryWeightLoss;

class InventoryStocks extends Model
{
    protected $fillable = [
        'received_item_id',
        'product_id',
        'conversion_id',
        'quantity',
        'retail_price',
        'wholesale_price',
        'expiration_date',
        'batch_code',
        'unit_cost',
        'notes',
        'is_archived',
        'is_expired',
    ];

    public function receivedItem()
    {
        return $this->belongsTo(ReceivedItem::class);
    }

    public function inventoryAdjustments()
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function conversion()
    {
        return $this->belongsTo(ProductConversion::class);
    }

    public function conversionsOut()
    {
        return $this->hasMany(ProductConversion::class, 'source_stock_id');
    }

    public function weightLosses()
    {
        return $this->hasMany(InventoryWeightLoss::class, 'inventory_stock_id');
    }
}
