<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListSupplier extends Model
{
    protected $fillable = [
        'name',
        'address',
        'contact_person',
        'contact_number',
        'email',
        'tin',
        'status_id',
    ];

    public function status()
    {
        return $this->belongsTo('App\Models\ListStatus', 'status_id');
    }
}
