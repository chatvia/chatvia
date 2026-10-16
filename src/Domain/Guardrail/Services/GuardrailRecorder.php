<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services;

use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Security\Services\VisitorFingerprint;

/**
 * Turns the verdicts in a {@see GuardResultData} into queued audit events,
 * deriving the turn's identity (agent / workspace / actor / conversation /
 * message / trace) from the {@see ChatContext}. Keeps the detection service pure
 * and the audit dispatch in one place. An anonymous visitor's turn also carries
 * their fingerprint, so the Guardrails page can show repeat attacks and block
 * the visitor; a chatter or staff turn does not need one.
 */
class GuardrailRecorder
{
    public function __construct(
        private readonly VisitorFingerprint $fingerprint,
    ) {}

    public function record(ChatContext $context, string $sample, GuardResultData $result): void
    {
        if (! $result->triggered()) {
            return;
        }

        $rd = $context->requestData;
        $agent = $rd->agent;
        $visitorKey = $rd->chatterId === null && $rd->actorUserId === null
            ? $this->fingerprint->for((int) $agent->workspace_id, $rd->ipAddress, $rd->userAgent)
            : null;

        foreach ($result->verdicts as $verdict) {
            GuardrailTriggered::dispatch(
                $agent->id,
                $agent->name,
                (int) $agent->workspace_id,
                $rd->actorUserId,
                $rd->actorName,
                $rd->conversationId,
                $verdict->stage === GuardStage::Output ? $context->assistantMessageId : $context->userMessageId,
                $context->assistantMessageId,
                $verdict->stage->value,
                $verdict->guardType->value,
                $verdict->outcome->value,
                $verdict->severity?->value,
                $verdict->detail,
                $sample,
                $rd->chatterId,
                $visitorKey,
            );
        }
    }
}
