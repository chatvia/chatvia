<?php

namespace Domain\Guardrail\Services;

use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Events\GuardrailTriggered;

/**
 * Turns the verdicts in a {@see GuardResultData} into queued audit events,
 * deriving the turn's identity (agent / workspace / actor / conversation /
 * message / trace) from the {@see ChatContext}. Keeps the detection service pure
 * and the audit dispatch in one place.
 */
class GuardrailRecorder
{
    public function record(ChatContext $context, string $sample, GuardResultData $result): void
    {
        if (! $result->triggered()) {
            return;
        }

        $rd = $context->requestData;
        $agent = $rd->agent;

        foreach ($result->verdicts as $verdict) {
            GuardrailTriggered::dispatch(
                $agent->id,
                $agent->name,
                (int) $agent->user_id,
                $rd->actorUserId,
                $rd->actorName,
                $rd->conversationId,
                $verdict->stage === GuardStage::Output ? $context->assistantMessageId : $context->userMessageId,
                $context->assistantMessageId,
                $verdict->stage->value,
                $verdict->guardType->value,
                $verdict->outcome->value,
                $verdict->severity,
                $verdict->detail,
                $sample,
            );
        }
    }
}
