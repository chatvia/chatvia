<?php

namespace Domain\Billing\Listeners;

use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Conversation\Events\AssistantReplyCompleted;

/**
 * Charges the workspace for a completed assistant reply: the model's base
 * cost plus one message per PDF page the turn sent to the model. The charge
 * itself runs on the queue; this listener only queues it, on the request.
 */
class ChargeAssistantReply
{
    public function handle(AssistantReplyCompleted $event): void
    {
        UpdateUserCreditsJob::dispatch(
            userId: $event->workspaceId,
            modelId: $event->llmId,
            additionalMessages: $event->documentPages,
        );
    }
}
