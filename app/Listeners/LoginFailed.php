<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LoginFailed
{
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Lockout $event): void
    {   
        $email = $event->request->input('email');
        $email_hash = hash('sha256', strtolower($email));
        $user = User::where('kradworkz', $email_hash)->first();
        $userId = $user ? $user->id : null;
        $ip = $this->request->ip();

        $existingEntry = \DB::table('authentication_logs')
        ->where('user_id', $userId)
        ->where('lockout_at','>',now())
        ->exists();
        if (!$existingEntry){
            \DB::table('authentication_logs')->insert([
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $this->request->userAgent(),
                'location' => json_encode(geoip()->getLocation($ip)->toArray()),
                'is_failed' => true,
                'lockout_at' => now()->addMinutes(1),
                'created_at' => now(),
            ]);
        }
    }
}
