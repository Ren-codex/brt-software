<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * A user's own theme colour.
 *
 * One colour is stored on the user (`users.theme_primary`) and shared with that
 * user's pages through HandleInertiaRequests; the browser derives the rest of
 * the palette from it. Saving with no colour puts them back on the default
 * green. Nobody else's screen is affected, which is why any signed-in user may
 * set their own.
 */
class SystemThemeController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            // Exactly #rrggbb. The value is written into a style attribute in
            // the browser, so nothing looser is accepted.
            'primary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $primary = isset($validated['primary']) ? strtolower($validated['primary']) : null;

        // forceFill: the column is deliberately not mass-assignable, so it can
        // only ever be set here, for the signed-in user themselves.
        $request->user()->forceFill(['theme_primary' => $primary])->save();

        return response()->json([
            'status' => true,
            'message' => $primary ? 'Theme saved.' : 'Theme reset to the default.',
            'primary' => $primary,
        ]);
    }

    /** A user's saved colour, or null for the default. Never anything but #rrggbb. */
    public static function for(?User $user): ?string
    {
        $value = $user?->theme_primary;

        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : null;
    }
}
