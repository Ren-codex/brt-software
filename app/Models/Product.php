<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'brand_id',
        'pack_size',
        'unit_id',
        'is_active',
    ];

    public function unit()
    {
        return $this->belongsTo('App\Models\ListUnit', 'unit_id');
    }

    public function brand()
    {
        return $this->belongsTo('App\Models\ListBrand', 'brand_id');
    }

    public function receivedItems()
    {
        return $this->hasMany('App\Models\ReceivedItem', 'product_id');
    }
}
