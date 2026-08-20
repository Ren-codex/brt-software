<?php

namespace App\Services\System\Permission;

use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Levels that describe doing work in a submodule. Each implies read access:
     * someone who encodes or approves in a submodule has to be able to open it,
     * and routes gate reads with 'view'. Without this, granting only 'encoder'
     * locked the holder out of the page entirely.
     *
     * This is one-directional — it never grants a write level. An encoder still
     * cannot approve, and a viewer still cannot encode.
     */
    private const WORKING_LEVELS = ['encoder', 'approver', 'releaser', 'void'];

    /** The granted levels that would satisfy a required level. */
    private function levelsSatisfying(string $required): array
    {
        $levels = [$required, 'admin'];

        if ($required === 'view') {
            $levels = array_merge($levels, self::WORKING_LEVELS);
        }

        return array_values(array_unique($levels));
    }

    /** Add the read access that working levels imply, for the shared map. */
    private function withImpliedLevels(array $granted): array
    {
        if (array_intersect($granted, self::WORKING_LEVELS) && !in_array('view', $granted, true)) {
            $granted[] = 'view';
        }

        return array_values(array_unique($granted));
    }

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
            ->whereIn('access_level', $this->levelsSatisfying($level))
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
                $map[$moduleKey][$subKey] = $this->withImpliedLevels($levels);
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
