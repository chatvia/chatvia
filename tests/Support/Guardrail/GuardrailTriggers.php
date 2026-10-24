<?php

declare(strict_types=1);

namespace Tests\Support\Guardrail;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Events\GuardrailTriggered;
use Illuminate\Support\Str;

/**
 * Builds the {@see GuardrailTriggered} event a guard fires for an agent: by
 * default PII masked in a visitor's message, with a short sample.
 */
final class GuardrailTriggers
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public static function event(
        Agent $agent,
        string $guardType = 'pii',
        string $outcome = 'redacted',
        string $stage = 'input',
        string $sample = 'sample',
        ?string $severity = null,
        array $detail = ['types' => ['email']],
    ): GuardrailTriggered {
        return new GuardrailTriggered(
            agentId: $agent->id,
            agentName: $agent->name,
            workspaceId: (int) $agent->workspace_id,
            userId: null,
            userName: null,
            conversationExternalId: (string) Str::uuid7(),
            messageId: (string) Str::uuid7(),
            traceId: 'trace-1',
            stage: $stage,
            guardType: $guardType,
            outcome: $outcome,
            severity: $severity,
            detail: $detail,
            sample: $sample,
        );
    }
}
