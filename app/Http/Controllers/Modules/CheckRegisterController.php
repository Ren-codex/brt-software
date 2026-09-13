<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Services\Modules\CheckForecastClass;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Http\Request;

/**
 * The owner's surface over the check register: every check in both directions,
 * and the two acts that move money — confirming one cleared, or recording that
 * it bounced.
 *
 * Deliberately separate from the rep's Sales-side view. A rep must not be able
 * to discharge their own check, which is the only thing that makes "cleared"
 * mean anything.
 */
class CheckRegisterController extends Controller
{
    public function __construct(
        private CheckRegisterClass $register,
        private CheckForecastClass $forecast,
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'direction' => $request->input('direction'),
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
            'count' => $request->input('count'),
        ];

        if ($request->input('option') === 'lists') {
            return response()->json($this->register->lists($filters));
        }

        if ($request->input('option') === 'forecast') {
            return response()->json($this->forecast->build());
        }

        return inertia('Modules/Accounting/CheckRegister', [
            'directions' => [Check::DIRECTION_RECEIVED, Check::DIRECTION_ISSUED],
            'statuses' => [Check::STATUS_PENDING, Check::STATUS_CLEARED, Check::STATUS_BOUNCED],
        ]);
    }

    public function confirm(int $id)
    {
        $check = Check::findOrFail($id);

        $this->register->markCleared($check);

        return response()->json([
            'data' => $check->fresh(),
            'message' => 'Check confirmed cleared.',
            'info' => 'The amount has been posted to the bank.',
        ]);
    }

    public function bounce(int $id, Request $request)
    {
        $data = $request->validate([
            'bounce_reason' => 'required|string|max:255',
        ]);

        $check = Check::findOrFail($id);

        $this->register->markBounced($check, $data['bounce_reason']);

        return response()->json([
            'data' => $check->fresh(),
            'message' => 'Check recorded as bounced.',
            'info' => 'The customer still owes this amount, and the rep who received it has been told.',
        ]);
    }
}
