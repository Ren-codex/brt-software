<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'so_number',
        'order_date',
        'payment_mode',
        'payment_term',
        'transferred_to',
        'transferred_at',
        'customer_id',
        'status_id',
        'total_amount',
        'total_discount',
        'added_by_id',
        'updated_by_id',
        'approved_by_id',
        'approved_at',
    ];

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function logs()
    {
        return $this->hasMany(PurchaseOrderLog::class, 'po_id');
    }
    
    public static function generateSONumber($date = null)
    {
        if ($date) {
            $year = date("y", strtotime($date));  // 'y' gives the last two digits of the year
            $month = date("m", strtotime($date));
        } else {
            $year = date("y", strtotime("now"));  // 'y' gives the last two digits of the year
            $month = date("m", strtotime("now"));
        }

        $count = self::whereYear('order_date', date("Y", strtotime($date ?? "now")))
                     ->whereMonth('order_date', $month)
                     ->count() + 1;

        return 'SO-' . $year . '-' . $month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
