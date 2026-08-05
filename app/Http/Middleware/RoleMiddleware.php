<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next,...$roles): Response
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        if (Auth::user()->roles()->where('user_roles.is_active', 1)->where('name', 'Super Admin')->exists()) {
            return $next($request);
        }

        if (!Auth::user()->roles()->where('user_roles.is_active', 1)->whereIn('name', $roles)->exists()) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
