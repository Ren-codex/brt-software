<?php

namespace App\Http\Controllers;

use App\Services\Approvals\ApprovalService;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The one screen an approver can work from.
 *
 * Follows the house `?option=` pattern: the bare GET renders the Inertia page,
 * `?option=lists` returns the rows as JSON for the page's own refreshes,
 * `?option=history&decision=approved|rejected` returns past decisions for the
 * Approved and Disapproved tabs, and
 * `?option=count` returns just the badge number for the bell.
 *
 * Access is not role-based. The page shows each user exactly the sources they
 * hold `approver` on, so a payroll approver sees payrolls and loans while a
 * warehouse approver sees purchase orders and stock returns. There is no
 * Administrator gate on the route because there does not need to be one — an
 * account with no approver grant anywhere simply sees an empty page.
 */
class ApprovalController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected ApprovalService $service) {}

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return response()->json($this->service->pending($request->user()));

            case 'count':
                return response()->json(['count' => $this->service->pendingCount($request->user())]);

            // The Approved and Disapproved tabs, loaded when opened.
            case 'history':
                $decision = $request->query('decision') === 'rejected' ? 'rejected' : 'approved';

                return response()->json($this->service->history($decision, $request->user()));

            default:
                $pending = $this->service->pending($request->user());

                return inertia('Modules/Approvals/Index', [
                    'initial' => $pending,
                    // Deep-link targets from a notification: which group to open
                    // and which row to highlight.
                    'focusSource' => $request->query('source'),
                    'focusId' => $request->query('focus'),
                ]);
        }
    }

    public function decide(Request $request, string $source, int $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approved,disapproved',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->service->decide(
                $source,
                $id,
                $validated['decision'],
                $validated['remarks'] ?? null,
            );
        } catch (ValidationException $e) {
            // A module's own rule refused it — a cash expense over the
            // cash-on-hand balance, say. Surface its message verbatim.
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'status' => false,
            ], 422);
        }

        return response()->json([
            'data' => $result['data'] ?? null,
            'message' => $result['message'] ?? 'Decision recorded.',
            'info' => $result['info'] ?? null,
            'status' => $result['status'] ?? true,
            'pending' => $this->service->pending($request->user()),
        ]);
    }
}
