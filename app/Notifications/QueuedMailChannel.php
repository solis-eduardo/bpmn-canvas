<?php

namespace App\Notifications;

use App\Models\User;

class QueuedMailChannel
{
    public function send(User $notifiable, PreferenceAwareNotification $notification): void
    {
        SendQueuedNotificationEmail::dispatch($notifiable, $notification);
    }
}
