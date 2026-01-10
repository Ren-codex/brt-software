<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'reason',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
