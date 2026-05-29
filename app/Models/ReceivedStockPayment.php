<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivedStockPayment extends Model
{
    protected $fillable = [
        'received_stock_id',
        'payment_date',
        'payment_mode',
        'amount_paid',
        'bank_name',
        'reference_number',
        'created_by_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2',
    ];

    public function receivedStock()
    {
        return $this->belongsTo(ReceivedStock::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
