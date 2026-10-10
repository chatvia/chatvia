<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An agent finished answering a visitor: a model really ran for this turn
 * (a Q&A answer counts too; a guardrail refusal does not). Counted against
 * the workspace's monthly message limit.
 */
final readonly class AssistantReplyCompleted
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,
        public string $assistantMessageId,
    ) {}
}
