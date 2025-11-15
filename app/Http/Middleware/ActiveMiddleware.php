<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if(auth()->check()) {
            if(auth()->user()->must_change) {
                return redirect()->intended(route('activation', absolute: false));
            }
            return $next($request);
        }
        return $next($request);
    }
}
