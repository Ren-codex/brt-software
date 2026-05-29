<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected NotificationService $service) {}

    public function index(Request $request)
    {
        $user          = $request->user();
        $notifications = $user->notifications()->latest()->take(10)->get();
        $unreadCount   = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? null,
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'unread_count'  => $unreadCount,
        ]);
    }

    public function markRead(Request $request, $id)
    {
        $result = $this->handleTransaction(fn() => $this->service->markRead($request, $id));

        return response()->json($result);
    }

    public function markAllRead(Request $request)
    {
        $result = $this->handleTransaction(fn() => $this->service->markAllRead($request));

        return response()->json($result);
    }
}
