<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'amount_paid',
        'payment_mode',
        'status_id',
        'customer_id',
        'ar_invoice_id',
        'remittance_id',
    ];

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function remittance()
    {
        return $this->belongsTo(Remittance::class, 'remittance_id');
    }
}