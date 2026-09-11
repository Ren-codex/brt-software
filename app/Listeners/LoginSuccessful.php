<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        // Writing the audit trail must never be able to fail a sign-in. Both
        // the public-IP lookup and the geo lookup call third-party HTTP
        // services, so an outage, a slow response or a local SSL problem would
        // otherwise turn every login into a 500.
        try {
            $ip = $this->resolveIpAddress();

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
     * The request IP is the local/proxy address, so the public address is
     * looked up externally when that is enabled. Any failure falls back to the
     * request IP rather than propagating.
     */
    private function resolveIpAddress(): string
    {
        $requestIp = $this->request->ip() ?: '127.0.0.1';

        if (! config('services.ip_lookup.enabled')) {
            return $requestIp;
        }

        try {
            $response = Http::timeout((int) config('services.ip_lookup.timeout', 3))
                ->get(config('services.ip_lookup.url'));

            if ($response->successful() && ($ip = $response->json('ip'))) {
                return $ip;
            }
        } catch (\Throwable $e) {
            Log::debug('Public IP lookup failed; using the request IP instead.', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $requestIp;
    }

    /** @return array<string, mixed> */
    private function resolveLocation(string $ip): array
    {
        if (! config('services.ip_lookup.enabled')) {
            return [];
        }

        try {
            return geoip()->getLocation($ip)->toArray();
        } catch (\Throwable $e) {
            Log::debug('Geo lookup failed; recording the login without a location.', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
