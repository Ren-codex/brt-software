<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    public const DIRECTION_RECEIVED = 'received';
    public const DIRECTION_ISSUED = 'issued';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_BOUNCED = 'bounced';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'direction', 'check_number', 'check_date', 'amount', 'bank_name',
        'bank_account_id', 'status', 'source_type', 'source_id', 'customer_id',
        'supplier_id', 'received_by_id', 'cleared_at', 'cleared_by_id',
        'bounced_at', 'bounced_by_id', 'bounce_reason', 'notes',
    ];

    protected $casts = [
        'check_date' => 'date',
        'cleared_at' => 'datetime',
        'bounced_at' => 'datetime',
    ];

    public function source()
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReceived($query)
    {
        return $query->where('direction', self::DIRECTION_RECEIVED);
    }

    public function scopeIssued($query)
    {
        return $query->where('direction', self::DIRECTION_ISSUED);
    }
}
