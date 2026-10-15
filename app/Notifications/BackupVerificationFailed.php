<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Sent to every Administrator when `backup:verify` could not restore the
 * latest backup artifact (ADR-0026).
 */
class BackupVerificationFailed extends PreferenceAwareNotification
{
    public function __construct(private readonly string $reason) {}

    protected function emailMessage(User $recipient): string
    {
        return __('messages.notification_backup_verification_failed', ['reason' => $this->reason]);
    }

    protected function emailUrl(): string
    {
        return route('admin.backups.index');
    }

    /**
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function toArray(object $notifiable): array
    {
        return ['reason' => $this->reason];
    }
}
