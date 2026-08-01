<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    protected $table = 'sales_return_items';

    protected $fillable = [
        'sales_order_item_id',
        'source_receipt_id',
        'return_quantity',
        'return_condition',
    ];

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
