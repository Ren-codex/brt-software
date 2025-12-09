<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'so_number',
        'so_date',
        'unit_cost',
        'quantity',
        'status_id',
        'product_id',
        'customer_id',
        'created_by_id',
    ];

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }


    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'so_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function logs()
    {
        return $this->hasMany(PurchaseOrderLog::class, 'po_id');
    }
}
