<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        if(Auth::check()){
            return redirect()->intended(route('dashboard', absolute: false));
        }else{
            return Inertia::render('Auth/Login', [
                'canResetPassword' => Route::has('password.request'),
                'status' => session('status')
            ]);
        }
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->put('two_factor_authenticated', false);

        if(\Auth::user()->is_active){
            if(\Auth::user()->must_change){
                return redirect()->intended(route('activation', absolute: false));
            }
            return redirect()->intended(route('dashboard', absolute: false));
        }else{
            return back()->withErrors([
                'email' => 'Account Locked, Please contact administrator.',
            ])->onlyInput('email');
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
