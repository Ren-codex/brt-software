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
        'is_active',
        'is_blacklisted',
       
    ];

}
