<?php

declare(strict_types=1);

namespace Domain\Guardrail\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\GuardAlert;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Notifications\GuardrailAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Emails the workspace owner about a blocked attack or a skipped check
 * ({@see GuardAlert}) when the agent's "alert me" setting is on. Throttled to
 * one email per agent and kind per hour, so an attack burst or an outage
 * sends one email, not hundreds. Queued, off the chat path.
 */
class SendGuardrailAlert implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

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
        $owner = $agent?->workspace?->owner;

        if ($owner === null || ! GuardrailSettingsData::fromAgent($agent)->alert_owner) {
            return;
        }

        // Cache::add is atomic: only the first trigger in the hour sends.
        if (! Cache::add('guardrail-alert-email:'.$agent->id.':'.$alert->value, true, now()->addHour())) {
            return;
        }

        $owner->notify(new GuardrailAlertNotification($agent, $alert));
    }
}
