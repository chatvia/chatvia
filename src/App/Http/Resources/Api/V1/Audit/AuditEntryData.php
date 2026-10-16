<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Audit;

use Carbon\CarbonInterface;
use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;
use Spatie\LaravelData\Data;

/**
 * One tool-call audit row (Governed MCP Gateway) over the public API. Rows
 * are immutable and identified by `trace_id` + `tool_call_id`; agent and user
 * names are denormalized so a row stays readable after either is deleted.
 */
class AuditEntryData extends Data
{
    public function __construct(
        /**
         * Correlates every row of one agent turn.
         */
        public string $trace_id,
        /**
         * The model's id for this tool call within the turn.
         */
        public string $tool_call_id,
        /**
         * The agent that made the call; null when the agent was deleted.
         */
        public ?string $agent_id,
        /**
         * The agent's name when the call was made.
         */
        public string $agent_name,
        /**
         * The signed-in chatter's name; null for an anonymous visitor.
         */
        public ?string $user_name,
        /**
         * The tool that was called.
         */
        public string $tool_name,
        /**
         * The tool's kind: `mcp` or `function`.
         */
        public ToolKind $tool_kind,
        /**
         * The catalog connector the tool belongs to, when it is one.
         */
        public ?string $connector_slug,
        /**
         * The connector's display name.
         */
        public ?string $connector_name,
        /**
         * What the gateway decided: `auto`, `approved`, `denied`, `rate_limited` or `not_offered`.
         */
        public ToolCallOutcome $outcome,
        /**
         * Whether the call needed a human approval checkpoint.
         */
        public bool $gated,
        /**
         * Whether the tool call errored.
         */
        public bool $is_error,
        /**
         * How long the tool call took, in milliseconds.
         */
        public ?int $latency_ms,
        /**
         * The call's arguments (possibly truncated at recording time).
         *
         * @var array<string, mixed>|null
         */
        public ?array $arguments,
        /**
         * The tool's result text (possibly truncated at recording time).
         */
        public ?string $result_text,
        /**
         * The conversation the call happened in.
         */
        public ?string $conversation_id,
        /**
         * When an approval decision was made, for gated calls.
         */
        public ?CarbonInterface $decided_at,
        /**
         * The approver's note, for gated calls.
         */
        public ?string $decision_note,
        /**
         * When the call was recorded.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(AgentActivity $activity): self
    {
        return new self(
            trace_id: $activity->trace_id,
            tool_call_id: $activity->tool_call_id,
            agent_id: $activity->relationLoaded('agent') ? $activity->agent?->external_id : null,
            agent_name: $activity->agent_name,
            user_name: $activity->user_name,
            tool_name: $activity->tool_name,
            tool_kind: $activity->tool_kind,
            connector_slug: $activity->connector_slug,
            connector_name: $activity->connector_name,
            outcome: $activity->outcome,
            gated: $activity->is_gated,
            is_error: $activity->is_error,
            latency_ms: $activity->latency_ms,
            arguments: $activity->arguments,
            result_text: $activity->result_text,
            conversation_id: $activity->conversation_external_id,
            decided_at: $activity->decided_at,
            decision_note: $activity->decision_note,
            created_at: $activity->created_at,
        );
    }
}
