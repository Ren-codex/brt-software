<?php

namespace App\Traits;

use App\Services\System\Permission\PermissionService;

trait AuthorizesPermission
{
    /**
     * Abort with 403 unless the current user holds $level for the given
     * module (and, optionally, submodule). For use inside controller
     * methods where the check can't be expressed as a single declarative
     * route middleware — e.g. several actions of different required
     * levels sharing one route (see PermissionMiddleware for the
     * route-middleware form used where the route IS single-purpose).
     */
    protected function authorizePermission(string $moduleKey, ?string $submoduleKey, string $level): void
    {
        $user = auth()->user();

        if (!$user || !app(PermissionService::class)->userHasAccess($user, $moduleKey, $submoduleKey, $level)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    /**
     * Allows an action when the user holds ANY of the given levels.
     *
     * Some actions are legitimately reachable by more than one kind of
     * authority — cancelling a sales order is the void-holder's job, but an
     * approver may also do it — and a single required level cannot express that
     * without either over-granting or taking the ability away from one of them.
     *
     * @param  array<int, string>  $levels
     */
    protected function authorizeAnyPermission(string $moduleKey, ?string $submoduleKey, array $levels): void
    {
        $user = auth()->user();
        $permissions = app(PermissionService::class);

        foreach ($levels as $level) {
            if ($user && $permissions->userHasAccess($user, $moduleKey, $submoduleKey, $level)) {
                return;
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
