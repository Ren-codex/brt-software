<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductConversion extends Model
{
    protected $fillable = [
        'source_stock_id',
        'output_stock_id',
        'source_qty_used',
        'conversion_ratio',
        'output_quantity',
        'reason',
        'converted_by_id',
        'conversion_date',
    ];

    public function sourceStock()
    {
        return $this->belongsTo(InventoryStocks::class, 'source_stock_id');
    }

    public function outputStock()
    {
        return $this->belongsTo(InventoryStocks::class, 'output_stock_id');
    }

    public function convertedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'converted_by_id');
    }
}
