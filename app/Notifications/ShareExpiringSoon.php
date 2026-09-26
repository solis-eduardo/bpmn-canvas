<?php

namespace App\Notifications;

use App\Models\Share;
use Illuminate\Notifications\Notification;

/**
 * Sent to a Diagram's Owner when one of their Shares is inside the
 * configured expiry-warning lead time (`shares:warn-expiring`, issue #21).
 * Database channel only, same as DiagramAccessRequested — this app has no
 * mail/broadcast transport wired up for notifications yet.
 */
class ShareExpiringSoon extends Notification
{
    public function __construct(
        private readonly Share $share,
    ) {}

    /**
     * $notifiable is required by Notification's abstract signature even
     * though this channel list doesn't vary per notifiable.
     *
     * @return array<int, string>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function via(object $notifiable): array
    {
        return ['database'];
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
