<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivedStock extends Model
{
    protected $fillable = [
        'po_id',
        'supplier_id',
        'received_date',
        'received_no',
        'received_by_id',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier()
    {
        return $this->belongsTo(ListSupplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(ReceivedItem::class, 'received_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }
}
