<?php

declare(strict_types=1);

namespace Domain\Conversation\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\HandoffRequested;
use Domain\Conversation\Notifications\HandoffRequestedNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells everyone who may take over the agent's chats that a visitor is
 * waiting. Queued, so the visitor's request returns at once.
 */
class NotifyHandoffRequested implements ShouldQueue
{
    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(HandoffRequested $event): void
    {
        $agent = $event->conversation->agent;

        if (! $agent instanceof Agent) {
            return;
        }

        Notification::send(
            $this->recipients->forAgent($agent, 'agent.chat.takeover'),
            new HandoffRequestedNotification($event->conversation, $agent),
        );
    }
}
