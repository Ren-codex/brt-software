<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListPayrollItem extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
