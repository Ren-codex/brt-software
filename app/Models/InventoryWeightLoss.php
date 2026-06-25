<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryWeightLoss extends Model
{
    protected $fillable = [
        'inventory_stock_id',
        'loss_kg',
        'affected_sacks',
        'loss_per_sack',
        'reason',
        'notes',
        'recorded_by_id',
        'recorded_at',
        'converted_at',
        'converted_by_id',
        'conversion_id',
    ];

    public function inventoryStock()
    {
        return $this->belongsTo(InventoryStocks::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by_id');
    }

    public function conversion()
    {
        return $this->belongsTo(ProductConversion::class, 'conversion_id');
    }

    public function isConverted(): bool
    {
        return !is_null($this->converted_at);
    }
}
