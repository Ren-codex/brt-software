<?php

namespace App\Services\Libraries;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;

class RolePermissionClass
{
    public function catalogForRole(int $roleId): array
    {
        $role = ListRole::findOrFail($roleId);
        $modules = Module::with('submodules')->orderBy('sort_order')->get();
        $grants = RolePermission::where('role_id', $roleId)->get();

        $grantsByKey = [];
        foreach ($grants as $grant) {
            $key = $grant->module_id . ':' . ($grant->submodule_id ?? 'null');
            $grantsByKey[$key][] = $grant->access_level;
        }

        return [
            'role' => ['id' => $role->id, 'name' => $role->name],
            'modules' => $modules->map(function (Module $module) use ($grantsByKey) {
                return [
                    'id' => $module->id,
                    'key' => $module->key,
                    'name' => $module->name,
                    'levels' => $grantsByKey[$module->id . ':null'] ?? [],
                    'submodules' => $module->submodules->map(function ($sub) use ($module, $grantsByKey) {
                        return [
                            'id' => $sub->id,
                            'key' => $sub->key,
                            'name' => $sub->name,
                            'levels' => $grantsByKey[$module->id . ':' . $sub->id] ?? [],
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    public function save(int $roleId, array $grants): array
    {
        $role = ListRole::findOrFail($roleId);

        RolePermission::where('role_id', $roleId)->delete();

        foreach ($grants as $grant) {
            RolePermission::create([
                'role_id' => $roleId,
                'module_id' => $grant['module_id'],
                'submodule_id' => $grant['submodule_id'] ?? null,
                'access_level' => $grant['access_level'],
            ]);
        }

        return [
            'data' => $this->catalogForRole($roleId),
            'message' => 'Permissions updated successfully!',
            'info' => "Permissions for {$role->name} have been saved.",
        ];
    }
}
