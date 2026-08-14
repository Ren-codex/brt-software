<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submodule extends Model
{
    protected $fillable = ['module_id', 'key', 'name', 'sort_order'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}
