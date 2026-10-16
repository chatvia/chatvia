<?php

declare(strict_types=1);

namespace Domain\Conversation\Listeners;

use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Notifications\HandoffRequestedNotification;
use Domain\Notification\Actions\ClearNotificationGroupAction;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Once a waiting conversation is taken over, handed back to the AI or ended,
 * nobody needs to act on it any more: its "visitor waiting" notifications are
 * marked read for everyone who has one.
 */
class ClearHandoffNotifications implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly ClearNotificationGroupAction $clear,
    ) {}

    public function handle(HandedOff $event): void
    {
        if ($event->status === HandoffStatus::Pending->value) {
            return;
        }

        $this->clear->execute(HandoffRequestedNotification::groupKey($event->conversationId));
    }
}
