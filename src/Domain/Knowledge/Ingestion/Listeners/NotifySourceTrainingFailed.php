<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Events\SourceTrainingFailed;
use Domain\Knowledge\Ingestion\Notifications\SourceTrainingNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who may train this kind of source on the agent that its
 * training failed, so someone can look at the reason and try again.
 */
class NotifySourceTrainingFailed implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(SourceTrainingFailed $event): void
    {
        $agent = Agent::query()->where('external_id', $event->agentExternalId)->first();

        if (! $agent instanceof Agent) {
            return;
        }

        Notification::send(
            $this->recipients->forAgent($agent, SourceTrainingNotification::permission($event->source)),
            SourceTrainingNotification::failed($event->source, $agent),
        );
    }
}
