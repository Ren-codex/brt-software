<?php

namespace App\Http\Middleware;

use App\Services\System\Permission\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function __construct(protected PermissionService $permissions)
    {
    }

    /**
     * Usage: 'permission:module,level' (module-wide) or
     * 'permission:module,submodule,level' (submodule-specific).
     */
    public function handle(Request $request, Closure $next, string $moduleKey, string $submoduleKeyOrLevel, ?string $level = null): Response
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        if ($level === null) {
            $submoduleKey = null;
            $requiredLevel = $submoduleKeyOrLevel;
        } else {
            $submoduleKey = $submoduleKeyOrLevel;
            $requiredLevel = $level;
        }

        if (!$this->permissions->userHasAccess(Auth::user(), $moduleKey, $submoduleKey, $requiredLevel)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
