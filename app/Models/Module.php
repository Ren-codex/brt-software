<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['key', 'name', 'sort_order'];

    public function submodules()
    {
        return $this->hasMany(Submodule::class)->orderBy('sort_order');
    }

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}
