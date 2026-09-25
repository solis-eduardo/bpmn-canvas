<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The bell (shared layout, every screen): unread count polling, a short
 * list of recent notifications for the dropdown, and the two "mark as
 * read" actions (issue #15). Every action here is scoped to the
 * authenticated User's own notifications — {@see resolveOwn()} is the only
 * place a notification is looked up, so nobody can read or dismiss
 * somebody else's.
 */
class NotificationController extends Controller
{
    /**
     * Polled by the bell to keep the badge current.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Recent notifications for the bell's dropdown, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(20)->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at,
                'data' => $notification->data,
                'url' => route('notifications.read', $notification->id),
            ]);

        return response()->json($notifications);
    }

    /**
     * Clicking a notification: marks it read and redirects to the
     * relevant screen (the Diagram's Collaborators panel — issue #11).
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $this->resolveOwn($request, $notification);

        $record->markAsRead();

        $diagram = Diagram::find($record->data['diagram_id'] ?? null);

        if (! $diagram) {
            return redirect()->route('diagrams.index');
        }

        return redirect(route('diagrams.show', $diagram->slug).'?open-panel=collaborators');
    }

    /**
     * "Mark all as read", called from the bell dropdown.
     */
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['marked' => true]);
    }

    private function resolveOwn(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->where('id', $id)->firstOrFail();
    }
}
