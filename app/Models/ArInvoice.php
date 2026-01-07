<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'status_id',
        'invoice_number',
        'invoice_date',
        'amount_paid',
        'balance_due',
        'total_discount',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'total_discount' => 'decimal:2',
    ];

    public function sales_order()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class);
    }

    public static function generateInvoiceNumber($date = null)
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $month = $date->format('m');

        $lastInvoice = self::whereYear('created_at', $year)
                          ->whereMonth('created_at', $month)
                          ->orderBy('id', 'desc')
                          ->first();

        $sequence = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;

        return 'AR-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
