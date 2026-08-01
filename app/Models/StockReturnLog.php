<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReturnLog extends Model
{
    protected $fillable = [
        'stock_return_id',
        'user_id',
        'action',
        'remarks',
    ];

    public function stockReturn()
    {
        return $this->belongsTo(StockReturn::class, 'stock_return_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
