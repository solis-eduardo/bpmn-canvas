<?php

namespace App\Notifications;

use App\Models\Diagram;
use App\Models\User;

/**
 * Sent to a Diagram's current Lock holder when another User requests the Lock
 * (CONTEXT.md, "Lock": "the holder is notified"). The Editor also shows a
 * banner on the next Heartbeat, but that only reaches a holder whose Editor
 * is still open — closing it never releases the Lock (ADR-0003), so the
 * notification is what reaches the holder who walked away.
 *
 * A request never moves the Lock by itself (ADR-0011): this only tells the
 * holder someone is waiting.
 */
class DiagramLockRequested extends PreferenceAwareNotification
{
    public function __construct(
        private readonly Diagram $diagram,
        private readonly User $requester,
    ) {}

    /** @SuppressWarnings("PHPMD.UnusedFormalParameter") */
    protected function emailMessage(User $recipient): string
    {
        return __('messages.notification_lock_requested', [
            'name' => $this->requester->name,
            'diagram' => $this->diagram->title,
        ]);
    }

    protected function emailUrl(): string
    {
        return route('diagrams.show', $this->diagram->slug);
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
