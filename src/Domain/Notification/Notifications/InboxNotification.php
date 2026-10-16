<?php

declare(strict_types=1);

namespace Domain\Notification\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Channels\InboxChannel;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Illuminate\Notifications\Notification;

/**
 * The base of every notification that lands in the dashboard's notification
 * center. It goes to the inbox only when the person keeps the bell on for its
 * category; a subclass may also send on other channels (mail, broadcast)
 * through {@see alsoVia()}, which the person's switches do not affect.
 *
 * Each subclass lives in the context that sends it and is sent by a listener,
 * never by an Action.
 */
abstract class InboxNotification extends Notification
{
    abstract public function kind(): NotificationKind;

    abstract public function toInbox(User $notifiable): NotificationMessageData;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->alsoVia($notifiable);

        if ($notifiable instanceof User && $notifiable->notificationPreferences()->for($this->kind()->category())->bell) {
            $channels[] = InboxChannel::class;
        }

        return $channels;
    }

    /**
     * The channels this notification uses besides the inbox.
     *
     * @return array<int, string>
     */
    protected function alsoVia(object $notifiable): array
    {
        return [];
    }

    protected function message(NotificationContentData $content, ?string $groupKey = null): NotificationMessageData
    {
        return new NotificationMessageData($this->kind(), $content, $groupKey);
    }
}
