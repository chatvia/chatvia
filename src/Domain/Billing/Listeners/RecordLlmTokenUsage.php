<?php

namespace Domain\Billing\Listeners;

use Domain\Billing\Services\UserCreditSystem;
use Domain\Llm\Events\LlmTokensConsumed;

/**
 * Add a unit of work's LLM token usage to the workspace owner's current billing
 * cycle (the `user_usages.tokens` accumulator). ANALYTICS only — the figure shown
 * on the usage dashboard; it never gates or bills (that's messages).
 */
class RecordLlmTokenUsage
{
    public function __construct(private readonly UserCreditSystem $creditSystem) {}

    public function handle(LlmTokensConsumed $event): void
    {
        $this->creditSystem->setUserId($event->workspaceId)->recordTokenUsage($event->tokens);
    }
}
