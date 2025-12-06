<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'customer_id',
        'so_number',
        'payment_mode',
        'order_date',   
        'status_id',
        'added_by_id',
        'transferred_to_id',
        'transferred_at',
        'payment_mode'
    ];

    public function items()
    {
        return $this->hasMany('App\Models\SalesOrderItem', 'sales_order_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo('App\Models\Customer', 'customer_id', 'id');
    }

    public function status()
    {
        return $this->belongsTo('App\Models\ListStatus', 'status_id', 'id');
    }

    public function added_by()
    {
        return $this->belongsTo('App\Models\User', 'added_by_id', 'id');
    }

    public function transferred_to()
    {
        return $this->belongsTo('App\Models\User', 'transferred_to_id', 'id');
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
    
        $count = self::whereYear('created_at', date("Y", strtotime($date ?? "now")))
                     ->whereMonth('created_at', $month)
                     ->count() + 1;
    
        return 'SO-' . $month . '-'.$year. '-'. str_pad($count, 4, '0', STR_PAD_LEFT);
    }

}
