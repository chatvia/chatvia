<?php

namespace Domain\Billing\Listeners;

use Domain\Llm\Events\LlmTokensConsumed;
use Illuminate\Support\Facades\Redis;

/**
 * Add a unit of work's LLM token usage to the workspace owner's current billing
 * cycle (the `user_usages.tokens` accumulator). ANALYTICS only — the figure shown
 * on the usage dashboard; it never gates or bills (that's messages).
 *
 * Buffered in Redis (fires inline for every LLM op); FlushTokenUsageBuffer
 * drains the hash to user_usages.tokens every minute. Loss-tolerant.
 */
class RecordLlmTokenUsage
{
    public const string BUFFER_KEY = 'token_usage_buffer';

    public function handle(LlmTokensConsumed $event): void
    {
        if ($event->tokens <= 0) {
            return;
        }

        Redis::hincrby(self::BUFFER_KEY, (string) $event->workspaceId, $event->tokens);
    }
}
