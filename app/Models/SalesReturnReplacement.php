<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnReplacement extends Model
{
    protected $table = 'sales_return_replacements';
    protected $fillable = ['sales_order_id', 'product_id', 'quantity', 'price', 'total_value', 'replaced_at', 'replaced_by_id'];

    public function product()  { return $this->belongsTo(Product::class); }
    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function replacedBy() { return $this->belongsTo(User::class, 'replaced_by_id'); }
}
