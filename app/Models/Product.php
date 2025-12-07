<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'brand_id',
        'pack_size',
        'unit_id',
        'is_active',
    ];

    public function unit()
    {
        return $this->belongsTo(ListUnit::class, 'unit_id');
    }

    public function brand()
    {
        return $this->belongsTo(ListBrand::class);
    }
}
