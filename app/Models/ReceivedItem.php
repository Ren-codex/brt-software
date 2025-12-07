<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivedItem extends Model
{
    protected $fillable = [
        'received_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'po_item_id',
    ];

    public function receivedStock()
    {
        return $this->belongsTo(ReceivedStock::class, 'received_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }
}
