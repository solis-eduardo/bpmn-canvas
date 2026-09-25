<?php

namespace App\Notifications;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Sent to a Diagram's Owner when a User requests Collaborator access
 * (DiagramCollaboratorController::store, via GrantOrRequestCollaborator —
 * only fired for a self-request, never a direct invite; see issue #15).
 * Database channel only: this app has no mail/broadcast transport wired up
 * for notifications yet, and the bell (polling the unread-count endpoint)
 * is the only surface the spec asks for.
 */
class DiagramAccessRequested extends Notification
{
    public function __construct(
        private readonly Diagram $diagram,
        private readonly User $requester,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'diagram_id' => $this->diagram->id,
            'diagram_slug' => $this->diagram->slug,
            'diagram_title' => $this->diagram->title,
            'requester_id' => $this->requester->id,
            'requester_name' => $this->requester->name,
        ];
    }
}
