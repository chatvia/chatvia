<?php

declare(strict_types=1);

namespace Domain\Llm\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace's LLM token usage was accumulated over a unit of work (one chat
 * turn, one ingestion job, one analysis pass). Carries the real prompt+completion
 * total from the provider usage payloads. Recorded for ANALYTICS only — see
 * Domain\Billing\Listeners\RecordLlmTokenUsage. Tokens never gate or bill.
 */
class LlmTokensConsumed
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,
        public int $tokens,
        /** A coarse label for the work that produced the tokens (chat, ingest, analysis...). */
        public ?string $purpose = null,
    ) {}
}
