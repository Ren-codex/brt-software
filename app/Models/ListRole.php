<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListRole extends Model
{
    use HasFactory;

    // Roles whose accounts may exist without an employee record, and whose
    // profile has nothing required.
    public const ADMIN_ROLES = ['Administrator', 'Super Admin', 'Mini Admin'];

    protected $fillable = [
        'name','type','definition','is_active'
    ];

    public function permissions()
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }
}
