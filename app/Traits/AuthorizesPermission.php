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
}
