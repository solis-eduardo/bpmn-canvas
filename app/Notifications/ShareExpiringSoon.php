<?php

namespace App\Notifications;

use App\Models\Share;
use App\Models\User;

/**
 * Sent to a Diagram's Owner when one of their Shares is inside the
 * configured expiry-warning lead time (`shares:warn-expiring`, issue #21).
 */
class ShareExpiringSoon extends PreferenceAwareNotification
{
    public function __construct(
        private readonly Share $share,
    ) {}

    protected function emailMessage(User $recipient): string
    {
        return __('messages.notification_share_expiring', [
            'diagram' => $this->share->diagram->title,
            'date' => $recipient->inDisplayTimezone($this->share->expires_at)->translatedFormat('M j, Y'),
        ]);
    }

    protected function emailUrl(): string
    {
        return route('diagrams.show', $this->share->diagram->slug);
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
            'share_id' => $this->share->id,
            'diagram_id' => $this->share->diagram->id,
            'diagram_slug' => $this->share->diagram->slug,
            'diagram_title' => $this->share->diagram->title,
            'expires_at' => $this->share->expires_at,
        ];
    }
}
