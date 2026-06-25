<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'code',
        'brand_id',
        'weight',
        'unit_id',
        'packaging_id',
        'is_active',
        'minimum_stock',
    ];

    public function unit()
    {
        return $this->belongsTo('App\Models\ListUnit', 'unit_id');
    }

    public function brand()
    {
        return $this->belongsTo('App\Models\ListBrand', 'brand_id');
    }

    public function packaging()
    {
        return $this->belongsTo('App\Models\ListPackaging', 'packaging_id');
    }

    public function receivedItems()
    {
        return $this->hasMany('App\Models\ReceivedItem', 'product_id');
    }
}
