<?php

namespace Domain\Billing\Actions\Credits;

use Domain\Billing\Listeners\RecordLlmTokenUsage;
use Illuminate\Support\Facades\Redis;

/**
 * Drains the Redis token-usage buffer filled by {@see RecordLlmTokenUsage}
 * into each workspace's current cycle — one UPDATE per workspace per run
 * instead of one per LLM call. Loss-tolerant (analytics only). Returns how
 * many workspaces were flushed.
 */
class FlushTokenUsageBufferAction
{
    public function __construct(
        private readonly RecordTokenUsageAction $recordTokenUsage,
    ) {}

    public function execute(): int
    {
        $buffered = Redis::hgetall(RecordLlmTokenUsage::BUFFER_KEY);

        foreach ($buffered as $workspaceId => $tokens) {
            // Delete before writing: increments that land meanwhile go into a
            // fresh entry for the next run.
            Redis::hdel(RecordLlmTokenUsage::BUFFER_KEY, (string) $workspaceId);

            $this->recordTokenUsage->execute((int) $workspaceId, (int) $tokens);
        }

        return count($buffered);
    }
}
