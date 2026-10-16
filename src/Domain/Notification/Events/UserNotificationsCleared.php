<?php

declare(strict_types=1);

namespace Domain\Notification\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A person cleared all their notifications ("Clear all" in the bell), so
 * every open tab empties its list and its badge.
 */
class UserNotificationsCleared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting;

    public function __construct(
        public int $userId,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Identity.Workspace.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'UserNotificationsCleared';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
