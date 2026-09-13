<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArInvoice extends Model
{
    use HasFactory;

    /**
     * Invoices that represent money a customer still owes.
     *
     * A cancelled invoice is excluded even though its balance should already be
     * zero: totalling `balance_due` unfiltered is what let two cancelled orders
     * overstate outstanding receivables by ₱52,040, and a scope is harder to
     * forget than a where clause at each call site.
     */
    public function scopeOutstanding($query)
    {
        return $query->whereHas('status', fn ($q) => $q->where('slug', '!=', 'cancelled'));
    }

    protected $fillable = [
        'sales_order_id',
        'status_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'amount_due',
        'amount_paid',
        'balance_due',
        'total_discount',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount_due'  => 'decimal:2',
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

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'ar_invoice_id');
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
