<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthenticationLog extends Model
{
    protected $table = 'authentication_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'location',
        'is_failed',
        'is_suspicious',
        'lockout_at',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
