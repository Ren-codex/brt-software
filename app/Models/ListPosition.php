<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListPosition extends Model
{
      protected $fillable = [
        'title',
        'short',
        'salary_id'
    ];

    public function salary()
    {
        return $this->belongsTo('App\Models\ListSalary', 'salary_id', 'id');
    }
}
