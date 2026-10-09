<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An agent finished answering a visitor: a model really ran for this turn
 * (a Q&A answer counts too; a guardrail refusal does not). Billing listens
 * and charges the workspace for the reply.
 *
 * `documentPages` is the page count of every PDF in the conversation: the
 * whole document is sent to the model again on every turn it is in context,
 * so billing charges for those pages on each such turn.
 */
final readonly class AssistantReplyCompleted
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,
        public int $llmId,
        public string $assistantMessageId,
        public int $documentPages = 0,
    ) {}
}
