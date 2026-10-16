<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Events\SourceTrained;
use Domain\Knowledge\Ingestion\Notifications\SourceTrainingNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who may train this kind of source on the agent that it
 * finished training. Queued, apart from the ingestion run.
 */
class NotifySourceTrained implements ShouldQueue
{
    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(SourceTrained $event): void
    {
        $agent = Agent::query()->where('external_id', $event->agentExternalId)->first();

        if (! $agent instanceof Agent) {
            return;
        }

        Notification::send(
            $this->recipients->forAgent($agent, SourceTrainingNotification::permission($event->source)),
            SourceTrainingNotification::trained($event->source, $agent),
        );
    }
}
