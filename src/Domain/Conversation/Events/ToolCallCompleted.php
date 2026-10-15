<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per tool-call decision at the dispatch choke point
 * ({@see ToolLoopRunner}), for every outcome — `auto` (ran without gating),
 * `approved`/`denied` (gated, resolved by the actor), `rate_limited`
 * (blocked by the access policy) and `not_offered` (a tool the agent was not
 * given on that turn, refused before any gate).
 *
 * It carries the full governance record as scalars (denormalized agent/user
 * names + connector) so the queued {@see RecordToolCallAudit} listener can
 * write one immutable `agent_activity` row without touching the streaming hot
 * path. `userName === null` means an anonymous visitor.
 */
final readonly class ToolCallCompleted
{
    use Dispatchable;

    public function __construct(
        public int $agentId,
        public string $agentName,
        public int $workspaceId,
        // Actor: a staff account (userId) or a chatter (chatterId), at most
        // one; both null for an anonymous visitor.
        public ?int $userId,
        public ?string $userName,
        // Correlation.
        public ?string $conversationExternalId,
        public string $traceId,
        public ?string $requestId,
        public string $toolCallId,
        // Tool + connector. toolName is UNPREFIXED (e.g. current-weather-tool).
        public string $toolName,
        public string $toolKind,            // mcp | function
        public ?string $connectorSlug,
        public ?string $connectorName,
        // Payload (redaction + size-cap applied by the recorder Action).
        public string $arguments,           // raw JSON args from the LLM
        public ?string $resultText,
        public bool $isError,
        // Outcome.
        public ?int $latencyMs,
        public bool $gated,
        public string $approvalOutcome,     // a ToolCallOutcome value
        public ?string $decidedAt = null,
        public ?int $chatterId = null,   // set when a gated call was resolved
        // What the person said when they decided out loud (a voice call's
        // yes), as stored after the guardrails.
        public ?string $decisionNote = null,
    ) {}
}
