<?php

declare(strict_types=1);

namespace Domain\Notification\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Some of a person's notifications were marked read — by them in another tab,
 * or for everyone when a teammate took the handoff they announced. Carries
 * the ids, or none when every notification was read, and the unread count
 * left, so each open tab sets its badge without asking.
 */
class UserNotificationsRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting;

    /**
     * @param  list<string>|null  $ids  the external ids read; null means all of them
     */
    public function __construct(
        public int $userId,
        public ?array $ids,
        public int $unread,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Identity.Workspace.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'UserNotificationsRead';
    }

    /** @return array{ids: list<string>|null, unread: int} */
    public function broadcastWith(): array
    {
        return [
            'ids' => $this->ids,
            'unread' => $this->unread,
        ];
    }
}
