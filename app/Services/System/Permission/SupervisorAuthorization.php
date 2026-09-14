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

    /**
     * Assumed when an action has no roles configured, so an action can never
     * become impossible to authorise through a gap in the settings.
     */
    private const DEFAULT_ROLE = 'Administrator';

    /**
     * Always able to authorise, whatever the settings say. Without this, a
     * configuration that excluded everyone would lock an action away with no
     * way back in.
     */
    private const ALWAYS_ALLOWED_ROLE = 'Super Admin';

    /**
     * @param  string  $identifier  The supervisor's username or their email.
     */
    public function issue(string $identifier, string $password, string $action, ?string $ip = null): string
    {
        $identifier = trim($identifier);
        $user = $this->findSupervisor($identifier);

        // Throttle the account itself whenever we can name it, so alternating
        // between a username and that same account's email cannot buy a second
        // set of attempts. Only an identifier matching nobody throttles on the
        // typed text.
        $key = $this->throttleKey($user ? 'id:' . $user->id : $identifier, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => 'Too many attempts. Try again in '
                    . ceil(RateLimiter::availableIn($key) / 60) . ' minute(s).',
            ]);
        }

        // One message for an unknown account and a bad password: saying which
        // was wrong tells an attacker which administrator accounts exist.
        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 900);

            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match an administrator account.',
            ]);
        }

        if (!$this->canAuthorize($user, $action)) {
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

    private function canAuthorize(User $user, string $action): bool
    {
        $allowed = $this->rolesFor($action);

        return $user->roles()
            ->where('user_roles.is_active', 1)
            ->where(function ($query) use ($allowed) {
                $query->whereIn('list_roles.id', $allowed)
                    ->orWhere('list_roles.name', self::ALWAYS_ALLOWED_ROLE);
            })
            ->exists();
    }

    /**
     * Role ids permitted to authorise this action, as configured. Falls back to
     * Administrator when nothing is set for it.
     *
     * @return array<int, int>
     */
    public function rolesFor(string $action): array
    {
        $configured = \Illuminate\Support\Facades\DB::table('supervisor_action_roles')
            ->where('action', $action)
            ->pluck('role_id')
            ->all();

        if ($configured) {
            return $configured;
        }

        return \App\Models\ListRole::where('name', self::DEFAULT_ROLE)->pluck('id')->all();
    }

    /**
     * The account behind a typed username or email.
     *
     * Username first: that column is unique, so it answers deterministically.
     * Email has no unique index on this table, so two accounts can carry the
     * same address -- and an ambiguous email is refused rather than resolved to
     * whichever row came back first, which would record the approval against
     * the wrong person.
     */
    private function findSupervisor(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        $user = User::where('username', $identifier)->first();

        if ($user) {
            return $user;
        }

        $matches = User::whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function cacheKey(string $token): string
    {
        return 'supervisor-auth:' . hash('sha256', $token);
    }

    /** @param  string  $subject  A resolved account ("id:7") or the typed text. */
    private function throttleKey(string $subject, ?string $ip): string
    {
        return 'supervisor|' . Str::lower($subject) . '|' . ($ip ?? 'unknown');
    }
}
