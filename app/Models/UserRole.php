<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'role_id', 'added_by_id', 'removed_by_id','removed_at'
    ];


    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    public function role()
    {
        return $this->belongsTo('App\Models\ListRole', 'role_id', 'id');
    }

    public function added()
    {
        return $this->belongsTo('App\Models\User', 'added_by_id', 'id');
    }

    public function removed()
    {
        return $this->belongsTo('App\Models\User', 'removed_by_id', 'id');
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('M d, Y g:i a', strtotime($value));
    }

    public function getCreatedAtAttribute($value)
    {
        return date('F d, Y g:i a', strtotime($value));
    }

    public function getRemovedAtAttribute($value)
    {
        return ($value) ? date('M d, Y g:i a', strtotime($value)) : '-';
    }
}
