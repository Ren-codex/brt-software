<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'po_date',
        'total_amount',
        'status_id',
        'supplier_id',
        'created_by_id',
    ];

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function supplier()
    {
        return $this->belongsTo(ListSupplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }
}
