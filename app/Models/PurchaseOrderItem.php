<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'po_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
