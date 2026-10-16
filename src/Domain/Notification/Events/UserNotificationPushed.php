<?php

declare(strict_types=1);

namespace Domain\Notification\Events;

use Domain\Notification\Data\UserNotificationData;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A notification reached a person's inbox: a new row, or more events gathered
 * into an unread one. Sent on the person's own channel so every open
 * dashboard tab updates its bell; only a new row (`is_new`) raises the count,
 * shows a desktop alert or plays a sound.
 */
class UserNotificationPushed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting;

    public function __construct(
        public int $userId,
        public UserNotificationData $notification,
        public bool $isNew,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Identity.Workspace.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'UserNotificationPushed';
    }

    /** @return array{notification: array<string, mixed>, is_new: bool} */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification->toArray(),
            'is_new' => $this->isNew,
        ];
    }
}
