<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorAuthorization extends Model
{
    protected $fillable = [
        'authorized_by_id',
        'requested_by_id',
        'action',
        'subject_type',
        'subject_id',
        'ip_address',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
