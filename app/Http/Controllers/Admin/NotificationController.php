<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;

class NotificationController extends Controller
{
    // ── Full Notifications List ──────────────────────────────────────
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('admin.notifications.index', compact('notifications'));
    }

    // ── Open A Notification: Mark Read + Go To Its Destination ─────
    public function open(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);

        $notification->markAsRead();

        return redirect($notification->link ?? route('admin.notifications.index'));
    }

    // ── Mark All As Read ─────────────────────────────────────────────
    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json(['success' => true]);
    }
}
