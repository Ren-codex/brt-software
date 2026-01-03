<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'pr_number',
        'po_number',
        'po_date',
        'total_amount',
        'status_id',
        'supplier_id',
        'created_by_id',
        'approved_by_id',
        'approved_date',
    ];

    public function status()
    {
        return $this->belongsTo(ListStatus::class, 'status_id');
    }

    public function supplier()
    {
        return $this->belongsTo(ListSupplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function logs()
    {
        return $this->hasMany(PurchaseOrderLog::class, 'po_id');
    }

    public function approved_by()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
