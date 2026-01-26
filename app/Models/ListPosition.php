<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListPosition extends Model
{
      protected $fillable = [
        'title',
        'slug',
        'rate_per_day',
        'is_regular',
        'is_active',
    ];
}
