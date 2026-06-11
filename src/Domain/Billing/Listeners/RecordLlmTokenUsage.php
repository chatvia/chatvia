<?php

namespace Domain\Billing\Listeners;

use Domain\Llm\Events\LlmTokensConsumed;
use Illuminate\Support\Facades\Redis;

/**
 * Add a unit of work's LLM token usage to the workspace owner's current billing
 * cycle (the `user_usages.tokens` accumulator). ANALYTICS only — the figure shown
 * on the usage dashboard; it never gates or bills (that's messages).
 *
 * Buffered, not written: this fires inline on the request path for every LLM op
 * (rewrite, embed, rerank, tool routing, ingestion analysis), and the previous
 * 3-query SELECT + increment made the workspace's single user_usages row a
 * serialization point under concurrent conversations. hincrby is atomic and
 * O(1); {@see \App\Console\Commands\FlushTokenUsageBuffer} drains the hash to
 * the DB every minute. The figure is loss-tolerant by design.
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
