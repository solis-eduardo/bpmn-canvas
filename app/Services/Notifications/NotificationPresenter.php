<?php

namespace App\Services\Notifications;

use App\Notifications\DiagramAccessRequested;
use App\Notifications\DiagramAccessRequestResolved;
use App\Notifications\DiagramCollaboratorInvited;
use App\Notifications\DiagramCollaboratorRemoved;
use App\Notifications\DiagramOwnershipTransferred;
use App\Notifications\WorkspaceMemberAdded;
use App\Notifications\WorkspaceMemberRemoved;
use App\Notifications\WorkspaceOwnershipTransferred;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    /**
     * Human-readable text for a notification, keyed by its type — the one
     * place that maps a notification's data to what the User reads. Empty for
     * a type it doesn't know.
     */
    public function messageFor(DatabaseNotification $notification): string
    {
        $data = $notification->data;

        return match ($notification->type) {
            DiagramAccessRequested::class => __('messages.notification_requested_access', [
                'name' => $data['requester_name'] ?? '',
                'diagram' => $data['diagram_title'] ?? '',
            ]),
            WorkspaceMemberAdded::class => __('messages.notification_workspace_added', [
                'workspace' => $data['workspace_name'] ?? '',
            ]),
            WorkspaceMemberRemoved::class => __('messages.notification_workspace_removed', [
                'workspace' => $data['workspace_name'] ?? '',
            ]),
            WorkspaceOwnershipTransferred::class => __('messages.notification_workspace_ownership_transferred', [
                'workspace' => $data['workspace_name'] ?? '',
            ]),
            DiagramAccessRequestResolved::class => __($data['approved']
                ? 'messages.notification_access_request_approved'
                : 'messages.notification_access_request_refused', [
                    'diagram' => $data['diagram_title'],
                ]),
            DiagramCollaboratorInvited::class => __('messages.notification_collaborator_invited', [
                'diagram' => $data['diagram_title'] ?? '',
            ]),
            DiagramCollaboratorRemoved::class => __('messages.notification_collaborator_removed', [
                'diagram' => $data['diagram_title'] ?? '',
            ]),
            DiagramOwnershipTransferred::class => __('messages.notification_diagram_ownership_transferred', [
                'diagram' => $data['diagram_title'] ?? '',
            ]),
            default => '',
        };
    }
}
