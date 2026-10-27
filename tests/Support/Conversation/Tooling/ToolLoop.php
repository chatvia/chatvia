<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Contracts\StreamTransformer;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\ToolCallAuditTrail;
use Domain\Conversation\Services\Tooling\ToolCallFactory;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Tooling\ToolResultGuard;
use Domain\Integration\Tool\Access\ToolAccessPolicy;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Access\ToolRateLimiter;
use Domain\Integration\Tool\Contracts\ToolDispatcher;
use Domain\Integration\Tool\Data\ToolDisplayData;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Domain\Integration\Tool\ValueObjects\ToolName;
use Domain\Llm\Services\AiCallTags;
use Illuminate\Support\Str;

/**
 * Builds the {@see ToolLoopRunner} the tests drive: the model stream, the
 * dispatcher and the approval policy are fakes; the approval store, the audit
 * trail and the guardrails are real, so a paused turn really leaves a
 * checkpoint. Also builds the offered tools and the stored pending calls the
 * runner reads.
 */
final class ToolLoop
{
    public static function runner(
        StreamTransformer $transformer,
        ToolDispatcher $dispatcher,
        ToolApprovalPolicy $policy,
    ): ToolLoopRunner {
        return new ToolLoopRunner(
            transformer: $transformer,
            dispatcher: $dispatcher,
            displayResolver: new class extends ToolDisplayResolver
            {
                public function __construct()
                {
                    // No parent constructor: every tool is a function named as it is.
                }

                public function resolve(Agent $agent, ToolName $tool): ToolDisplayData
                {
                    return new ToolDisplayData(kind: ToolKind::Function, label: $tool->value);
                }
            },
            accessPolicy: new ToolAccessPolicy($policy, new ToolRateLimiter),
            approvalStore: new ToolApprovalStore,
            auditTrail: new ToolCallAuditTrail,
            resultGuard: app(ToolResultGuard::class),
            tags: new AiCallTags,
            calls: new ToolCallFactory,
        );
    }

    /**
     * OpenAI-shaped definitions of the tools offered on the turn, as the tool
     * selector hands them to the runner.
     *
     * @return list<array<string, mixed>>
     */
    public static function tools(string ...$names): array
    {
        return array_values(array_map(static fn (string $name): array => [
            'type' => 'function',
            'function' => ['name' => $name, 'description' => $name, 'parameters' => ['type' => 'object', 'properties' => []]],
        ], $names));
    }

    /** A call of a paused turn as its checkpoint stores it: gated or not. */
    public static function pendingCall(string $name, bool $gated, string $arguments = '{}', string $id = 'call_1'): PendingToolCallData
    {
        return PendingToolCallData::fromArray([
            'tool_call_id' => $id,
            'name' => $name,
            'arguments' => $arguments,
            'requires_approval' => $gated,
            'kind' => 'function',
            'label' => Str::headline($name),
            'icon_url' => null,
            'brand_color' => null,
            'reason' => $gated ? ToolLoopRunner::APPROVAL_REASON : null,
        ]);
    }
}
