<?php

namespace Domain\Conversation\Data\Audit;

use Domain\Conversation\Models\AgentActivity;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the Governed MCP Gateway audit log, shared by the account
 * (Settings → Governance) and per-agent Audit viewers. The live avatar comes
 * from the eager-loaded `user` relation when present; rows without a user (or
 * a deleted user) fall back to the denormalized name. `arguments_preview`
 * carries the text when the arguments were cut or were not JSON.
 */
#[TypeScript]
class ToolCallAuditRowData extends Data
{
    /**
     * @param  array<string, mixed>|null  $arguments
     */
    public function __construct(
        public int $id,
        public ?string $created_at,
        public ToolCallOutcome $outcome,
        public ?int $agent_id,
        public string $agent_name,
        public ?string $agent_external_id,
        public ?int $user_id,
        public ?string $user_name,
        public ?string $avatar,
        public string $trace_id,
        public string $tool_name,
        public ToolKind $tool_kind,
        public ?string $connector_slug,
        public ?string $connector_name,
        public bool $is_error,
        public bool $gated,
        public ?int $latency_ms,
        public ?array $arguments,
        public ?string $arguments_preview,
        public bool $arguments_truncated,
        public ?string $result_text,
        public ?string $conversation_external_id,
        public ?string $decided_at,
        public ?string $decision_note,
    ) {}

    public static function fromModel(AgentActivity $activity): self
    {
        return new self(
            id: $activity->id,
            created_at: $activity->created_at?->toIso8601String(),
            outcome: $activity->outcome,
            agent_id: $activity->agent_id,
            agent_name: $activity->agent_name,
            agent_external_id: $activity->relationLoaded('agent') ? $activity->agent?->external_id : null,
            user_id: $activity->user_id,
            user_name: $activity->user_name,
            avatar: $activity->relationLoaded('user') ? $activity->user?->avatar : null,
            trace_id: $activity->trace_id,
            tool_name: $activity->tool_name,
            tool_kind: $activity->tool_kind,
            connector_slug: $activity->connector_slug,
            connector_name: $activity->connector_name,
            is_error: $activity->is_error,
            gated: $activity->is_gated,
            latency_ms: $activity->latency_ms,
            arguments: $activity->arguments,
            arguments_preview: $activity->arguments_preview,
            arguments_truncated: $activity->arguments_truncated,
            result_text: $activity->result_text,
            conversation_external_id: $activity->conversation_external_id,
            decided_at: $activity->decided_at?->toIso8601String(),
            decision_note: $activity->decision_note,
        );
    }
}
