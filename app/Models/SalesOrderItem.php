<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity',
        'price',
        'batch_code',
        'discount_per_unit'
    ];

    public function sales_order()
    {
        return $this->belongsTo('App\Models\SalesOrder', 'sales_order_id', 'id')->with('items.product');
    }

    public function batch_code()
    {
        return $this->belongsTo('App\Models\ReceivedStock', 'batch_code', 'batch_code');
    }
}
