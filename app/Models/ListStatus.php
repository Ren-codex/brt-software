<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListStatus extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'text_color',
        'bg_color',
    ];
}
