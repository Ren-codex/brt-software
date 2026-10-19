<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoginFailed
{
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Lockout $event): void
    {
        // As with a successful sign-in, the lockout audit entry is best-effort:
        // the geo lookup is a third-party HTTP call and must not be able to
        // turn a throttled login into a 500.
        try {
            $email = $event->request->input('email');
            $user = User::where('email', strtolower((string) $email))->first();
            $userId = $user ? $user->id : null;
            $ip = $this->request->ip() ?: '127.0.0.1';

            $existingEntry = DB::table('authentication_logs')
                ->where('user_id', $userId)
                ->where('lockout_at', '>', now())
                ->exists();

            if (! $existingEntry) {
                DB::table('authentication_logs')->insert([
                    'user_id' => $userId,
                    'ip_address' => $ip,
                    'user_agent' => $this->request->userAgent(),
                    'location' => json_encode($this->resolveLocation($ip)),
                    'is_failed' => true,
                    'lockout_at' => now()->addMinutes(1),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not record the failed-login audit entry.', [
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
            Log::debug('Geo lookup failed; recording the lockout without a location.', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
