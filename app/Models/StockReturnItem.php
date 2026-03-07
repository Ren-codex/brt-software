<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_return_id',
        'po_item_id',
        'quantity',
        'returned_quantity',
        'remarks',
        'status_id',
    ];

    public function stockReturn()
    {
        return $this->belongsTo(StockReturn::class, 'stock_return_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }
}
