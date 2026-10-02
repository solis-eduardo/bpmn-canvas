<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Searches a User's own notifications by their message text. The text is
 * built per locale from the notification data, so matching happens on the
 * rendered message rather than in SQL.
 */
class NotificationSearch
{
    public function __construct(private readonly NotificationPresenter $presenter) {}

    /**
     * The $user's notifications whose message contains $term
     * (case-insensitive), newest first. Never touches anyone else's.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function matching(User $user, string $term): Collection
    {
        return $user->notifications()->latest()->cursor()
            ->filter(fn (DatabaseNotification $notification) => mb_stripos($this->presenter->messageFor($notification), $term) !== false)
            ->values()
            ->collect();
    }
}
