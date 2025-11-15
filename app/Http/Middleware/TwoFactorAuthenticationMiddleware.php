<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthenticationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
     
        if(auth()->check()) {
            if(auth()->user()->two_factor_secret !== null && auth()->user()->two_factor_confirmed_at !== null) {
                if($request->session()->has('two_factor_authenticated')){
                    if(!$request->session()->get('two_factor_authenticated') === true){
                        return redirect()->intended(route('twofactor', absolute: false));
                    }
                }
            }

            if($request->session()->has('is_locked')){
                if($request->session()->get('is_locked') === true){
                    return redirect()->intended(route('password.confirm', absolute: false));
                }
            }
        }
        return $next($request);
    }
}
