<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\Workspace;
use App\Notifications\DiagramAccessRequested;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Notifications\WorkspaceOwnershipTransferred;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The bell (shared layout, every screen): unread count polling, a short
 * list of recent notifications for the dropdown, and the two "mark as
 * read" actions (issue #15) — plus the full history screen (issue #16)
 * reached from the bell's "view all" link. Every action here is scoped to
 * the authenticated User's own notifications — {@see resolveOwn()} is the
 * only place a notification is looked up, so nobody can read or dismiss
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
     * Recent notifications for the bell's dropdown, newest first. Shares
     * {@see present()} with {@see history()} so both surfaces build the
     * "message for a notification" text the same way, in one place.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(20)->get()
            ->map(fn (DatabaseNotification $notification) => $this->present($notification));

        return response()->json($notifications);
    }

    /**
     * The full history screen reached from the bell: every notification
     * the User has ever received, newest first, paginated, marking which
     * are unread. Each entry links to {@see read()} — the same target-link
     * the bell's dropdown uses — so clicking it marks it read and jumps to
     * the relevant screen.
     */
    public function history(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification) => $this->present($notification));

        return view('notifications.history', compact('notifications'));
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

        if ($diagram) {
            return redirect(route('diagrams.show', $diagram->slug).'?open-panel=collaborators');
        }

        $workspace = Workspace::find($record->data['workspace_id'] ?? null);

        if ($workspace) {
            return redirect()->route('workspaces.index');
        }

        return redirect()->route('diagrams.index');
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

    /**
     * @return array{id: string, message: string, read: bool, created_at: ?Carbon, url: string}
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'message' => $this->message($notification),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
            'url' => route('notifications.read', $notification->id),
        ];
    }

    /**
     * Human-readable text for a notification, keyed by its type — the one
     * place that maps a notification's data to what the User reads, shared
     * by both the bell's dropdown and the history screen.
     */
    private function message(DatabaseNotification $notification): string
    {
        return match ($notification->type) {
            DiagramAccessRequested::class => __('messages.notification_requested_access', [
                'name' => $notification->data['requester_name'] ?? '',
                'diagram' => $notification->data['diagram_title'] ?? '',
            ]),
            WorkspaceMemberAdded::class => __('messages.notification_workspace_added', [
                'workspace' => $notification->data['workspace_name'] ?? '',
            ]),
            WorkspaceMemberRemoved::class => __('messages.notification_workspace_removed', [
                'workspace' => $notification->data['workspace_name'] ?? '',
            ]),
            WorkspaceOwnershipTransferred::class => __('messages.notification_workspace_ownership_transferred', [
                'workspace' => $notification->data['workspace_name'] ?? '',
            ]),
            default => '',
        };
    }
}
