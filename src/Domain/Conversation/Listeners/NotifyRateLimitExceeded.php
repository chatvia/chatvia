<?php

declare(strict_types=1);

namespace Domain\Conversation\Listeners;

use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Notifications\RateLimitExceededNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who manage the agent's protection that its rate limit is
 * turning messages away. The event fires on every blocked message, so at most
 * one notification per agent is queued every ten minutes; the decision is a
 * single atomic cache check on the request, before anything is queued.
 */
class NotifyRateLimitExceeded implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function shouldQueue(AgentRateLimitExceeded $event): bool
    {
        return Cache::add("agent-rate-limit-inbox:{$event->agent->id}", true, now()->addMinutes(10));
    }

    public function handle(AgentRateLimitExceeded $event): void
    {
        Notification::send(
            $this->recipients->forAgent($event->agent, 'agent.identity.manage'),
            new RateLimitExceededNotification($event->agent, $event->limitPerMinute),
        );
    }
}
