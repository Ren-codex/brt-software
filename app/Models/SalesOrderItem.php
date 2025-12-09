<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity',
        'unit_cost',
        'status_id'
    
    ];

    public function sales_order()
    {
        return $this->belongsTo('App\Models\SalesOrder', 'sales_order_id', 'id');
    }
}
