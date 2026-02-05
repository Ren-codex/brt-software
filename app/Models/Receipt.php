<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'amount_paid',
        'balance_due',
        'payment_mode',
        'billing_account',
        'status_id',
        'customer_id',
        'ar_invoice_id',
        'remittance_id',
    ];

    public function arInvoice()
    {
        return $this->belongsTo('App\Models\ArInvoice', 'ar_invoice_id');
    }

    public function status()
    {
        return $this->belongsTo('App\Models\ListStatus', 'status_id');
    }

    public function customer()
    {
        return $this->belongsTo('App\Models\Customer', 'customer_id');
    }

    public function remittance()
    {
        return $this->belongsTo('App\Models\Remittance', 'remittance_id');
    }

    public static function generateReceiptNumber($date = null)
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $month = $date->format('m');

        $lastReceipt = self::whereYear('created_at', $year)
                          ->whereMonth('created_at', $month)
                          ->orderBy('id', 'desc')
                          ->first();

        $sequence = $lastReceipt ? intval(substr($lastReceipt->receipt_number, -4)) + 1 : 1;

        return 'RCP-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
