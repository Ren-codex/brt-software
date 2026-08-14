<?php

namespace App\Services\System\Permission;

use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    public function userHasAccess(User $user, string $moduleKey, ?string $submoduleKey, string $level): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $roleIds = $this->activeRoleIds($user);
        if ($roleIds->isEmpty()) {
            return false;
        }

        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            return false;
        }

        $submoduleId = null;
        if ($submoduleKey !== null) {
            $submodule = $module->submodules()->where('key', $submoduleKey)->first();
            if (!$submodule) {
                return false;
            }
            $submoduleId = $submodule->id;
        }

        return RolePermission::whereIn('role_id', $roleIds)
            ->where('module_id', $module->id)
            ->where(function ($query) use ($submoduleId) {
                $query->whereNull('submodule_id');
                if ($submoduleId !== null) {
                    $query->orWhere('submodule_id', $submoduleId);
                }
            })
            ->whereIn('access_level', array_unique([$level, 'admin']))
            ->exists();
    }

    /**
     * Build the full permission map for a user, shaped for Inertia sharing:
     * ['sales' => ['sales_orders' => ['view','encoder'], '_module' => ['admin']]]
     * '_module' holds module-wide (submodule_id null) grants.
     */
    public function userPermissionMap(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return $this->fullAccessMap();
        }

        $roleIds = $this->activeRoleIds($user);
        if ($roleIds->isEmpty()) {
            return [];
        }

        $grants = RolePermission::whereIn('role_id', $roleIds)
            ->with(['module', 'submodule'])
            ->get();

        $map = [];
        foreach ($grants as $grant) {
            if (!$grant->module) {
                continue;
            }
            $moduleKey = $grant->module->key;
            $subKey = $grant->submodule->key ?? '_module';
            $map[$moduleKey][$subKey][] = $grant->access_level;
        }

        foreach ($map as $moduleKey => $subs) {
            foreach ($subs as $subKey => $levels) {
                $map[$moduleKey][$subKey] = array_values(array_unique($levels));
            }
        }

        return $map;
    }

    protected function isSuperAdmin(User $user): bool
    {
        return $user->roles()->where('user_roles.is_active', 1)->where('name', 'Super Admin')->exists();
    }

    protected function activeRoleIds(User $user): Collection
    {
        return $user->roles()->where('user_roles.is_active', 1)->pluck('list_roles.id');
    }

    protected function fullAccessMap(): array
    {
        $map = [];
        foreach (Module::all() as $module) {
            $map[$module->key]['_module'] = RolePermission::LEVELS;
        }

        return $map;
    }
}
