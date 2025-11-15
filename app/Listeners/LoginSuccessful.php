<?php

namespace App\Listeners;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class LoginSuccessful
{
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Login $event): void
    {
        $user = $event->user;
        // $ip = $this->request->ip();

        $response = Http::get('https://api.ipify.org?format=json');
        if ($response->successful()) {
            $ipAddress = $response->json()['ip'];
            $ip = $ipAddress;
        }else{
            $ip = '127.0.0.1';
        }
        
        \DB::table('authentication_logs')->insert([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $this->request->userAgent(),
            'location' => json_encode(geoip()->getLocation($ip)->toArray()),
            'is_failed' => false,
            'lockout_at' => null,
            'created_at' => now(),
        ]);
    }
}
