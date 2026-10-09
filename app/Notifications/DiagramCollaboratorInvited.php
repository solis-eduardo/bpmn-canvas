<?php

namespace App\Notifications;

use App\Models\Collaborator;
use App\Models\Diagram;
use Illuminate\Notifications\Notification;

class DiagramCollaboratorInvited extends Notification
{
    public function __construct(
        private readonly Diagram $diagram,
        private readonly string $role = Collaborator::ROLE_COLLABORATOR,
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
            'diagram_id' => $this->diagram->id,
            'diagram_slug' => $this->diagram->slug,
            'diagram_title' => $this->diagram->title,
            'role' => $this->role,
        ];
    }
}
