<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A person's own notifications.
 *
 * Scoped to the signed-in user by the relationship itself, so there is no way to
 * read someone else's however the request is shaped.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'read' => $notification->read_at !== null,
                'sent' => $notification->created_at
                    ->setTimezone(config('salon.timezone'))->diffForHumans(),
                // Written by the notification itself, and deliberately thin.
                'data' => $notification->data,
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();

        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
