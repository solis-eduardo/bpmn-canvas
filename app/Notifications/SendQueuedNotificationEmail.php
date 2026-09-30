<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendQueuedNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly User $notifiable,
        private readonly PreferenceAwareNotification $notification,
    ) {}

    public function handle(MailChannel $mailChannel): void
    {
        if (! $this->notifiable->email_notifications_enabled) {
            return;
        }

        $mailChannel->send($this->notifiable, $this->notification);
    }
}
