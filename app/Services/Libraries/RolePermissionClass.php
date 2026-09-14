<?php

namespace App\Services\Libraries;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Services\System\Permission\SupervisorActions;
use Illuminate\Support\Facades\DB;

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

        $authorized = DB::table('supervisor_action_roles')
            ->where('role_id', $roleId)
            ->pluck('action')
            ->all();

        return [
            'role' => ['id' => $role->id, 'name' => $role->name],

            // Which guarded actions this role may authorise for somebody else.
            // Separate from the grants above: a grant says what you may do, this
            // says whose password unlocks somebody else's doing it.
            'authorizations' => array_map(
                fn ($action) => $action + ['assigned' => in_array($action['key'], $authorized, true)],
                SupervisorActions::all()
            ),
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

    /**
     * @param  array|null  $authorizations  Action keys this role may authorise, or
     *                                      null when the caller did not speak to
     *                                      them at all — see below.
     */
    public function save(int $roleId, array $grants, ?array $authorizations = null): array
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

        // null means the request never mentioned authorizations, which is what a
        // stale cached bundle posting the old payload shape looks like. Leave
        // them alone rather than reading silence as "none of them".
        if ($authorizations !== null) {
            $this->syncAuthorizations($roleId, $authorizations);
        }

        return [
            'data' => $this->catalogForRole($roleId),
            'message' => 'Permissions updated successfully!',
            'info' => "Permissions for {$role->name} have been saved.",
        ];
    }

    /** Replace this role's authorising rights with exactly the actions given. */
    private function syncAuthorizations(int $roleId, array $actions): void
    {
        DB::table('supervisor_action_roles')->where('role_id', $roleId)->delete();

        $actions = array_values(array_unique(array_filter($actions, SupervisorActions::exists(...))));

        if ($actions === []) {
            return;
        }

        $now = now();
        DB::table('supervisor_action_roles')->insert(array_map(
            fn ($action) => ['action' => $action, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now],
            $actions
        ));
    }
}
