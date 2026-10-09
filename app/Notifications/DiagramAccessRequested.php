<?php

namespace App\Notifications;

use App\Models\Diagram;
use App\Models\User;

/**
 * Sent to a Diagram's Owner when a User requests Collaborator access
 * (CollaboratorController::store, via CollaboratorService::requestAccess —
 * only fired for a self-request, never a direct invite; see issue #15).
 */
class DiagramAccessRequested extends PreferenceAwareNotification
{
    public function __construct(
        private readonly Diagram $diagram,
        private readonly User $requester,
    ) {}

    /** @SuppressWarnings("PHPMD.UnusedFormalParameter") */
    protected function emailMessage(User $recipient): string
    {
        return __('messages.notification_requested_access', [
            'name' => $this->requester->name,
            'diagram' => $this->diagram->title,
        ]);
    }

    protected function emailUrl(): string
    {
        return route('diagrams.show', $this->diagram->slug).'?open-panel=collaborators';
    }

    /**
     * $notifiable is required by the database channel's expected signature
     * even though the payload doesn't vary per notifiable.
     *
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
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
