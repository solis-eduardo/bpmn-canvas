<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

abstract class PreferenceAwareNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && $notifiable->email_notifications_enabled) {
            return ['database', QueuedMailChannel::class];
        }

        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new InvalidArgumentException('Email notifications require a User recipient.');
        }

        return (new MailMessage)
            ->subject(__('messages.notification_email_subject'))
            ->greeting(__('messages.notification_email_greeting', ['name' => $notifiable->name]))
            ->line($this->emailMessage())
            ->action(__('messages.notification_email_action'), $this->emailUrl());
    }

    abstract protected function emailMessage(): string;

    abstract protected function emailUrl(): string;
}
