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

    public static function getID($title){
        $position = self::where('title', $title)->first();
        return $position ? $position->id : null;
    }
}
