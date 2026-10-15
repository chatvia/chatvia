<?php

declare(strict_types=1);

namespace Domain\Guardrail\Listeners;

use Domain\Guardrail\Enums\GuardAlert;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Writes a blocked attack (prompt injection, leaked system prompt) to the
 * workspace's security audit log, which also delivers it to the SIEM webhook
 * when one is set. Only attacks go there: masked personal data and refused
 * topics are everyday events that belong in the Guardrails Activity log.
 * The metadata names the guard and where it ran, never the message text.
 */
class RecordGuardrailAttack implements ShouldQueue
{
    public function viaQueue(): string
    {
        return (string) config('chatvia.guardrails.queue', 'analytics');
    }

    public function handle(GuardrailTriggered $event): void
    {
        if (GuardAlert::for($event) !== GuardAlert::Attack) {
            return;
        }

        event(SecurityEventOccurred::for(
            SecurityAuditAction::GuardrailAttackBlocked,
            $event->workspaceId,
            SecurityActorData::system(),
            targetType: 'agent',
            targetId: $event->agentId,
            targetLabel: $event->agentName,
            metadata: array_filter([
                'guard_type' => $event->guardType,
                'stage' => $event->stage,
                'severity' => $event->severity,
                'pattern' => $event->detail['pattern'] ?? null,
                'tool' => $event->detail['tool'] ?? null,
                'conversation_id' => $event->conversationExternalId,
            ]),
            outcome: 'blocked',
        ));
    }
}
