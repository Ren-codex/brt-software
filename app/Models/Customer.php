<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'address',
        'tin',
        'contact_number',   
        'email',
        'is_active',
        'status_id',
        'is_regular',
        'is_blacklisted',
        'added_by_id'
    ];

    public function status()
    {
        return $this->belongsTo('App\Models\ListStatus', 'status_id', 'id');
    }



}
