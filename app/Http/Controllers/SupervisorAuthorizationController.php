<?php

namespace App\Http\Controllers;

use App\Services\System\Permission\SupervisorActions;
use App\Services\System\Permission\SupervisorAuthorization;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The only place a supervisor's password is ever sent. Business endpoints take
 * the resulting token instead, so a password never travels to, or gets logged
 * by, a sales-order or return request.
 */
class SupervisorAuthorizationController extends Controller
{
    public function store(Request $request, SupervisorAuthorization $authorizer)
    {
        $data = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'action' => 'required|string',
        ]);

        // An unlisted action is refused, so an override can never be minted for
        // something nobody chose to guard.
        if (!SupervisorActions::exists($data['action'])) {
            throw ValidationException::withMessages([
                'action' => 'That action does not take a supervisor authorisation.',
            ]);
        }

        $token = $authorizer->issue(
            $data['username'],
            $data['password'],
            $data['action'],
            $request->ip()
        );

        return response()->json([
            'token' => $token,
            'expires_in' => 300,
            'message' => 'Authorised.',
        ]);
    }
}
