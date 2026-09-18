<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line the customer turned away at the door. Counting these per customer
 * and per product is the reason they are recorded rather than quietly edited
 * out of the order.
 */
class SalesOrderDeliveryRefusal extends Model
{
    protected $fillable = [
        'sales_order_id',
        'sales_order_item_id',
        'product_id',
        'ordered_quantity',
        'accepted_quantity',
        'refused_quantity',
        'batch_code',
        'reason',
        'recorded_by_id',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
