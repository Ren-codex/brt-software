<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoginSuccessful
{
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Login $event): void
    {
        // Writing the audit trail must never be able to fail a sign-in. The geo
        // lookup calls a third-party HTTP service, so an outage, a slow
        // response or a local SSL problem would otherwise turn every login
        // into a 500.
        try {
            // The visitor's own address. Asking an external "what is my IP"
            // service from here only ever returns the server's address, which
            // logged every sign-in from the host's datacenter. Behind a proxy
            // or CDN, set TRUSTED_PROXIES so this reads X-Forwarded-For.
            $ip = $this->request->ip() ?: '127.0.0.1';

            DB::table('authentication_logs')->insert([
                'user_id' => $event->user->id,
                'ip_address' => $ip,
                'user_agent' => $this->request->userAgent(),
                'location' => json_encode($this->resolveLocation($ip)),
                'is_failed' => false,
                'lockout_at' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not record the successful-login audit entry.', [
                'user_id' => $event->user->id ?? null,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Private and loopback addresses have no place to look up, and GeoIP
     * answers a miss with its configured default (New Haven, US), which must
     * not be recorded as where someone signed in from.
     *
     * @return array<string, mixed>
     */
    private function resolveLocation(string $ip): array
    {
        if (! config('services.ip_lookup.enabled')
            || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [];
        }

        try {
            $location = geoip()->getLocation($ip);

            return $location->default ? [] : $location->toArray();
        } catch (\Throwable $e) {
            Log::debug('Geo lookup failed; recording the login without a location.', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
