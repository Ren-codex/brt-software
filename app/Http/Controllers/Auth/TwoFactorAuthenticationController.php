<?php

namespace App\Http\Controllers\Auth;

use Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Google2FA as Google2FAA;
use PragmaRX\Google2FAQRCode\Google2FA;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TwoFactorAuthenticationController extends Controller
{
    public function index(Request $request){
        if($request->session()->get('two_factor_authenticated')){
            return redirect()->intended(route('dashboard', absolute: false));
        }else{
            return inertia('Auth/TwoFactorChallenge');
        }
    }

    public function store(Request $request){
        $user = User::find(\Auth::user()->id);
        if($request->recovery_code){
            $my_codes = Crypt::decrypt($user->two_factor_recovery_codes);
            $my_codes = collect(json_decode($my_codes));
            $code = collect($my_codes)->first(function ($code) use ($request){
                return hash_equals($code, $request->recovery_code) ? $code : null;
            });
            if($code){
                $new = encrypt(str_replace(
                    $code,
                    $this->generate(),
                    decrypt($user->two_factor_recovery_codes)
                ));
                $user->update(['two_factor_recovery_codes' => $new]);
                $request->session()->put('two_factor_authenticated', true);
                return redirect()->intended(route('dashboard', absolute: false));
            }else{
                return redirect()->back()->withErrors([
                    'recovery_code' => 'The provided recovery code was invalid.'
                ]);
            }
        }else{
            $key = Crypt::decrypt($user->two_factor_secret);
            $result =  Google2FAA::verifyKey($key, $request->code);
            if($result){
                $request->session()->put('two_factor_authenticated', true);
                return redirect()->intended(route('dashboard', absolute: false));
            }else{
                return redirect()->back()->withErrors([
                    'code' => 'The provided two factor authentication code was invalid.'
                ]);
            }
        }
    }

    public function enable(){
        $google2fa = new Google2FA();
        $key = $google2fa->generateSecretKey();
        $user = User::find(\Auth::user()->id)->update(['two_factor_secret' => Crypt::encrypt($key)]);
        $url = $google2fa->getQRCodeInline('BRT',\Auth::user()->email, $key);
      
        return [
            'key' => $key,
            'url' => $url
        ];
    }

    public function confirm(Request $request){
        $result =  Google2FAA::verifyKey($request->key, $request->code);
        if($result){
            $user = User::find(\Auth::user()->id)->update(['two_factor_confirmed_at' => now()]);
        }
        return response()->json([
            'status' => $result,
            'message' => ($result) ? '' : 'The provided two factor authentication code was invalid.'
        ]);
    }

    public function disable(){
        $user = User::find(\Auth::user()->id)->update([
            'two_factor_confirmed_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null
        ]);
    }

    public function fetch(Request $request){
        $user = User::find(\Auth::user()->id);
        $key = Crypt::decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();
        $url = $google2fa->getQRCodeInline('BRT',\Auth::user()->email, $key);
      
        return [
            'key' => $key,
            'url' => $url
        ];
    }

    public function recovery(){
        $user = User::find(\Auth::user()->id);
        if(!$user->two_factor_recovery_codes){
            $codes = json_encode(Collection::times(8, function () {
                return $this->generate();
            })->all());
            $user->update(['two_factor_recovery_codes' => Crypt::encrypt($codes)]);
        }else{
            $codes = Crypt::decrypt($user->two_factor_recovery_codes);
        }
        return $codes;
    }

    public function regenerate(){
        $user = User::find(\Auth::user()->id);
        $codes = json_encode(Collection::times(8, function () {
            return $this->generate();
        })->all());
        $user->update(['two_factor_recovery_codes' => Crypt::encrypt($codes)]);

        return $codes;
    }

    public function generate(){
        return Str::random(10).'-'.Str::random(10);
    }
}
