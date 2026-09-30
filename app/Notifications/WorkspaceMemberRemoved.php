<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Notifications\Notification;

/**
 * Sent to a User when they are removed from a Workspace.
 */
class WorkspaceMemberRemoved extends Notification
{
    public function __construct(
        private readonly Workspace $workspace,
    ) {}

    /**
     * @return array<int, string>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function toArray(object $notifiable): array
    {
        return [
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
        ];
    }
}
