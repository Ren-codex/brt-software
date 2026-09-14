<?php

namespace App\Services\System\Permission;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A supervisor standing at the keyboard authorises an action with their own
 * credentials.
 *
 * This replaces three typed-word gates ("type CANCEL to proceed") that lived
 * entirely in the browser, so anyone posting to the endpoint directly walked
 * past them. Credentials are verified here, on the server, and the action
 * cannot proceed without a token this class issued.
 *
 * Deliberate choices:
 *
 * - Valid credentials are not authority. The account must hold the
 *   Administrator role; a correct password from a Sales Rep is refused.
 * - Attempts are rate limited per username and address. Without that, any
 *   logged-in rep has an unlimited password-guessing oracle against the
 *   administrator accounts.
 * - The token is single use and bound to one action, so an override granted to
 *   cancel one order cannot quietly cancel a second.
 * - The password is never stored, never logged, and never leaves this class.
 */
class SupervisorAuthorization
{
    /** Long enough to walk over and type; short enough not to linger. */
    private const TOKEN_TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 5;

    /** Roles allowed to authorise. Super Admin outranks Administrator everywhere else. */
    private const AUTHORIZING_ROLES = ['Administrator', 'Super Admin'];

    public function issue(string $username, string $password, string $action, ?string $ip = null): string
    {
        $key = $this->throttleKey($username, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => 'Too many attempts. Try again in '
                    . ceil(RateLimiter::availableIn($key) / 60) . ' minute(s).',
            ]);
        }

        $user = User::where('username', $username)->first();

        // One message for a bad username and a bad password: saying which was
        // wrong tells an attacker which administrator accounts exist.
        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 900);

            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match an administrator account.',
            ]);
        }

        if (!$this->canAuthorize($user)) {
            RateLimiter::hit($key, 900);

            throw ValidationException::withMessages([
                'username' => 'That account is not an administrator, so it cannot authorise this.',
            ]);
        }

        RateLimiter::clear($key);

        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'authorized_by_id' => $user->id,
            'requested_by_id' => Auth::id(),
            'action' => $action,
            'ip' => $ip,
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * Spend a token on the action it was issued for, and write the audit row.
     * Returns the authorising user's id.
     */
    public function consume(string $token, string $action, ?string $subjectType = null, ?int $subjectId = null): int
    {
        $payload = Cache::pull($this->cacheKey($token));

        if (!$payload) {
            throw ValidationException::withMessages([
                'supervisor_token' => 'That authorisation has expired or was already used. Ask for it again.',
            ]);
        }

        if (($payload['action'] ?? null) !== $action) {
            throw ValidationException::withMessages([
                'supervisor_token' => 'That authorisation was given for a different action.',
            ]);
        }

        \App\Models\SupervisorAuthorization::create([
            'authorized_by_id' => $payload['authorized_by_id'],
            'requested_by_id' => $payload['requested_by_id'] ?? null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => $payload['ip'] ?? null,
            'used_at' => now(),
        ]);

        return (int) $payload['authorized_by_id'];
    }

    private function canAuthorize(User $user): bool
    {
        return $user->roles()
            ->where('user_roles.is_active', 1)
            ->whereIn('list_roles.name', self::AUTHORIZING_ROLES)
            ->exists();
    }

    private function cacheKey(string $token): string
    {
        return 'supervisor-auth:' . hash('sha256', $token);
    }

    private function throttleKey(string $username, ?string $ip): string
    {
        return 'supervisor|' . Str::lower($username) . '|' . ($ip ?? 'unknown');
    }
}
