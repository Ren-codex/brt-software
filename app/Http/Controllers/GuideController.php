<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Http\Request;

/**
 * Who is responsible for what, for the in-app module guides.
 *
 * The guide text is written once, but the roles named beside it are read from
 * `role_permissions` every time. A guide that listed roles in its own text
 * would go stale the first time somebody changed a role under
 * Libraries → Roles, and a guide that names the wrong person is worse than one
 * that names nobody.
 *
 * Open to any signed-in user: it says which *roles* do what, never which
 * people, and knowing who to ask is the point of a guide.
 */
class GuideController extends Controller
{
    public function roles(Request $request)
    {
        $module = Module::with(['submodules' => fn ($q) => $q->orderBy('sort_order')])
            ->where('key', $request->query('module'))
            ->first();

        if (! $module) {
            return response()->json(['module' => null, 'areas' => []]);
        }

        // Every grant on this module, from roles that are still active.
        $grants = RolePermission::with('role')
            ->where('module_id', $module->id)
            ->whereHas('role', fn ($q) => $q->where('is_active', 1))
            ->get();

        // A grant with no submodule covers the whole module.
        $moduleWide = $grants->whereNull('submodule_id');

        $areas = $module->submodules->map(fn ($submodule) => [
            'key' => $submodule->key,
            'name' => $submodule->name,
            'roles' => $this->rolesFrom($moduleWide->concat($grants->where('submodule_id', $submodule->id))),
        ]);

        // A module with no parts of its own is one area.
        if ($areas->isEmpty()) {
            $areas = collect([[
                'key' => null,
                'name' => $module->name,
                'roles' => $this->rolesFrom($moduleWide),
            ]]);
        }

        return response()->json([
            'module' => ['key' => $module->key, 'name' => $module->name],
            'areas' => $areas->values(),
        ]);
    }

    /** Collapse grants into one entry per role, with its levels in a fixed order. */
    private function rolesFrom($grants): array
    {
        $order = array_flip(['admin', 'approver', 'releaser', 'encoder', 'void', 'view']);

        return $grants
            ->groupBy(fn ($grant) => $grant->role->name)
            ->map(fn ($roleGrants, $name) => [
                'name' => $name,
                'levels' => $roleGrants->pluck('access_level')->unique()
                    ->sortBy(fn ($level) => $order[$level] ?? 99)->values()->all(),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }
}
