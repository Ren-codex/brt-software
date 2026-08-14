<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','type','definition','is_active'
    ];

    public function permissions()
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }
}
