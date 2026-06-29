<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnHistory extends Model
{
    protected $table = 'sales_return_history';

    protected $fillable = [
        'sales_order_id',
        'sales_order_item_id',
        'product_id',
        'quantity',
        'condition',
        'unit_price',
        'total_value',
        'returned_at',
        'approved_by_id',
    ];

    protected $casts = [
        'returned_at' => 'date',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
