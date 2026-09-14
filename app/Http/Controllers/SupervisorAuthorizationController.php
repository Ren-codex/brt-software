<?php

namespace App\Http\Controllers;

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
    /** Actions an override can be granted for. An unlisted action is refused. */
    private const ACTIONS = [
        'sales.credit_sale',
        'sales.approve_return',
    ];

    public function store(Request $request, SupervisorAuthorization $authorizer)
    {
        $data = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'action' => 'required|string',
        ]);

        if (!in_array($data['action'], self::ACTIONS, true)) {
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
