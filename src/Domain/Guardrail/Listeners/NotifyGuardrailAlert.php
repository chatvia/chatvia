<?php

declare(strict_types=1);

namespace Domain\Guardrail\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\GuardAlert;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Notifications\GuardrailInboxNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Puts a blocked attack or a skipped check ({@see GuardAlert}) in the
 * notification center of everyone who may see the agent's guardrails. Unlike
 * the owner's email ({@see SendGuardrailAlert}) it needs no switch and is not
 * throttled: repeats gather into one unread line. Queued, off the chat path.
 */
class NotifyGuardrailAlert implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.guardrails.queue', 'analytics');
    }

    public function handle(GuardrailTriggered $event): void
    {
        $alert = GuardAlert::for($event);

        if (! $alert instanceof GuardAlert || $event->agentId === null) {
            return;
        }

        $agent = Agent::query()->find($event->agentId);

        if (! $agent instanceof Agent) {
            return;
        }

        Notification::send(
            $this->recipients->forAgent($agent, 'agent.guardrails.view'),
            new GuardrailInboxNotification($agent, $alert, $event->guardType, $event->detail['failure'] ?? null),
        );
    }
}
