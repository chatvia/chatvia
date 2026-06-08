<?php

namespace Domain\Conversation\Events;

use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per tool-call decision at the dispatch choke point
 * ({@see ToolLoopRunner}), for every outcome — `auto` (ran without gating),
 * `approved`/`denied` (gated, resolved by the actor), and `rate_limited`
 * (blocked by the access policy).
 *
 * It carries the full governance record as scalars (denormalized agent/user
 * names + connector) so the queued {@see RecordToolCallAudit} listener can
 * write one immutable `agent_activity` row without touching the streaming hot
 * path. `userName === null` means an anonymous visitor.
 */
final class ToolCallCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $agentId,
        public readonly string $agentName,
        public readonly int $workspaceId,
        // Actor (a chatter). Null = anonymous visitor.
        public readonly ?int $userId,
        public readonly ?string $userName,
        // Correlation.
        public readonly ?string $conversationExternalId,
        public readonly string $traceId,
        public readonly ?string $requestId,
        public readonly string $toolCallId,
        // Tool + connector. toolName is UNPREFIXED (e.g. current-weather-tool).
        public readonly string $toolName,
        public readonly string $toolKind,            // mcp | function
        public readonly ?string $connectorSlug,
        public readonly ?string $connectorName,
        // Payload (redaction + size-cap applied by the recorder Action).
        public readonly string $arguments,           // raw JSON args from the LLM
        public readonly ?string $resultText,
        public readonly bool $isError,
        // Outcome.
        public readonly ?int $latencyMs,
        public readonly bool $gated,
        public readonly string $approvalOutcome,     // auto | approved | denied | rate_limited
        public readonly ?string $decidedAt = null,   // set when a gated call was resolved
    ) {}
}
