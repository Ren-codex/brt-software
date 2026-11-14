<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'po_id',
        'product_id',
        'supplier_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'status',
        'received_quantity',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id');
    }

    public function supplier()
    {
        return $this->belongsTo(ListSupplier::class, 'supplier_id');
    }

    public function receivedItems()
    {
        return $this->hasMany(ReceivedItem::class, 'po_item_id');
    }
}
