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
        'payment_mode',
        'amount_paid',
        'bank_name',
        'reference_number',
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

    public function payments()
    {
        return $this->hasMany(ReceivedStockPayment::class)
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }
}
