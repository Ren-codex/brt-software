<?php

namespace App\Http\Controllers;

use App\Services\Approvals\ApprovalService;
use App\Services\NotificationService;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use HandlesTransaction;

    public function __construct(
        protected NotificationService $service,
        protected ApprovalService $approvals,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        // 15 rather than 10: the panel is now tall enough to show them, and an
        // approval backlog is easy to push past ten rows.
        $notifications = $user->notifications()->latest()->take(15)->get();
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'unread_count' => $unreadCount,
            // Live count of what is actually still pending, which is not the
            // same as unread notifications: a notification stays read after
            // someone opens it, and a record stays pending until it is actioned.
            'approval_count' => $this->approvals->pendingCount($user),
        ]);
    }

    public function markRead(Request $request, $id)
    {
        $result = $this->handleTransaction(fn () => $this->service->markRead($request, $id));

        return response()->json($result);
    }

    public function markAllRead(Request $request)
    {
        $result = $this->handleTransaction(fn () => $this->service->markAllRead($request));

        return response()->json($result);
    }
}
