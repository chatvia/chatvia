<?php

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Models\GuardrailEvent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the per-agent Guardrails trigger log. The live avatar comes from
 * the eager-loaded `user` relation; visitor rows (no user) fall back to the
 * denormalized name. `offender` says who caused an abusive trigger, whether
 * they are blocked and how often they tried lately — null for triggers no one
 * can be blocked for.
 */
#[TypeScript]
class GuardrailEventRowData extends Data
{
    /**
     * @param  array<string, mixed>|null  $detail
     */
    public function __construct(
        public int $id,
        public string $external_id,
        public ?string $created_at,
        public ?int $agent_id,
        public string $agent_name,
        public ?string $agent_external_id,
        public ?int $user_id,
        public ?string $user_name,
        public ?string $avatar,
        public GuardStage $stage,
        public GuardType $guard_type,
        public string $guard_label,
        public GuardOutcome $outcome,
        public ?GuardSeverity $severity,
        public ?array $detail,
        public ?string $snippet,
        public ?string $trace_id,
        public ?string $conversation_external_id,
        public ?string $visitor_key,
        public ?GuardrailOffenderData $offender,
    ) {}

    public static function fromModel(GuardrailEvent $event, ?GuardrailOffenderData $offender = null): self
    {
        return new self(
            id: $event->id,
            external_id: $event->external_id,
            created_at: $event->created_at?->toIso8601String(),
            agent_id: $event->agent_id,
            agent_name: $event->agent_name,
            agent_external_id: $event->relationLoaded('agent') ? $event->agent?->external_id : null,
            user_id: $event->user_id,
            user_name: $event->user_name,
            avatar: $event->relationLoaded('user') ? $event->user?->avatar : null,
            stage: $event->stage,
            guard_type: $event->guard_type,
            guard_label: $event->guard_type->label(),
            outcome: $event->outcome,
            severity: $event->severity,
            detail: $event->detail,
            snippet: $event->snippet,
            trace_id: $event->trace_id,
            conversation_external_id: $event->conversation_external_id,
            visitor_key: $event->visitor_key,
            offender: $offender,
        );
    }
}
