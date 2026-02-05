<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'so_number',
        'order_date',
        'customer_id',
        'status_id',
        'sub_status_id',
        'total_amount',
        'total_discount',
        'added_by_id',
        'updated_by_id',
        'transferred_to',
        'transferred_at',
        'approved_by_id',
        'approved_at',
        'sales_rep_id',
        'driver_id',
        'payment_mode',
        'due_date',
    ];

    protected $casts = [
        'order_date' => 'date',
        'transferred_at' => 'date',
        'approved_at' => 'date',
        'total_amount' => 'decimal:2',
        'total_discount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function status()
    {
        return $this->belongsTo(ListStatus::class);
    }

    public function subStatus()
    {
        return $this->belongsTo(ListStatus::class, 'sub_status_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function transferredTo()
    {
        return $this->belongsTo(User::class, 'transferred_to');
    }

    public function salesRep()
    {
        return $this->belongsTo(Employee::class, 'sales_rep_id');
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function arInvoices()
    {
        return $this->hasMany(ArInvoice::class);
    }

    public static function generateSoNumber($date = null)
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $month = $date->format('m');

        $lastSo = self::whereYear('created_at', $year)
                      ->whereMonth('created_at', $month)
                      ->orderBy('id', 'desc')
                      ->first();

        $sequence = $lastSo ? intval(substr($lastSo->so_number, -4)) + 1 : 1;

        return 'SO-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
