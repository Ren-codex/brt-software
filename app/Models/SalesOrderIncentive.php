<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderIncentive extends Model
{
    protected $fillable = [
        'sales_order_id',
        'employee_id',
        'sold_quantity',
        'product_total_kg',
        'amount',
        'payroll_id',
        'status',
    ];

    protected $casts = [
        'sold_quantity' => 'integer',
        'product_total_kg' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
