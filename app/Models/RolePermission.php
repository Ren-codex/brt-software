<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    public const LEVELS = ['encoder', 'approver', 'releaser', 'void', 'view', 'admin'];

    protected $fillable = ['role_id', 'module_id', 'submodule_id', 'access_level'];

    public function role()
    {
        return $this->belongsTo(ListRole::class, 'role_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function submodule()
    {
        return $this->belongsTo(Submodule::class);
    }
}
