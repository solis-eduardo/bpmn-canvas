<?php

namespace App\Notifications;

use App\Models\CommentThread;
use App\Models\Diagram;
use App\Models\User;

/**
 * Sent to a Diagram's Diagram Owner and to the other participants of a
 * Comment Thread when someone adds a Comment (CONTEXT.md, "Comment Thread";
 * ADR-0022). It names who commented and where, never what was written, so a
 * recipient who has since lost access to the Diagram learns nothing from it.
 */
class DiagramCommentPosted extends PreferenceAwareNotification
{
    public function __construct(
        private readonly Diagram $diagram,
        private readonly CommentThread $thread,
        private readonly User $author,
    ) {}

    /** @SuppressWarnings("PHPMD.UnusedFormalParameter") */
    protected function emailMessage(User $recipient): string
    {
        return __('messages.notification_comment_posted', [
            'name' => $this->author->name,
            'diagram' => $this->diagram->title,
        ]);
    }

    protected function emailUrl(): string
    {
        return $this->url();
    }

    /** The Diagram, with the Comments panel open on this Thread. */
    private function url(): string
    {
        return route('diagrams.show', $this->diagram->slug).'?'.http_build_query([
            'open-panel' => 'comments',
            'thread' => $this->thread->id,
        ]);
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
            'thread_id' => $this->thread->id,
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
        ];
    }
}
