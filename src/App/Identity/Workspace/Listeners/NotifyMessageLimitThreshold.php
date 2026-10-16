<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Events\MessageLimitThresholdReached;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\MessageLimitNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the workspace owner that the workspace is close to, or at, its
 * monthly message limit. Queued, off the reply path.
 */
class NotifyMessageLimitThreshold implements ShouldQueue
{
    public function handle(MessageLimitThresholdReached $event): void
    {
        $owner = $event->limit->workspace?->owner;

        if (! $owner instanceof User) {
            return;
        }

        $owner->notify(new MessageLimitNotification($event->limit, $event->percent));
    }
}
