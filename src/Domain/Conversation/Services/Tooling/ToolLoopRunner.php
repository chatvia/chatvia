<?php

namespace Domain\Conversation\Services\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Sse\ToolCallPhase;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Tool\Access\ToolAccessPolicy;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolAccessDecision;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Domain\Llm\Contracts\LlmProviderInterface;
use Generator;
use Illuminate\Support\Facades\Log;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * The provider-agnostic agentic loop. Streams an LLM completion, drains any
 * emitted tool_calls, runs each through the governance gate (approval / rate
 * limit), dispatches the allowed ones, and iterates until the model produces a
 * final answer or the configured iteration cap kicks in.
 *
 * Every tool-call decision — auto, approved, denied, or rate_limited — emits a
 * {@see ToolCallCompleted} event so the Governed MCP Gateway can record an
 * immutable audit row off the hot path.
 *
 * Shared by every source that needs the loop — `LlmSource` runs it on the
 * agent's configured provider, `PdfSource` runs it on the document LLM with a
 * PDF-augmented user turn. Each source owns its own start/finish envelope; the
 * runner only handles the iteration and tool plumbing.
 *
 * A "pending call" is the array shape below. It is stored as JSON on a
 * ToolApprovalCheckpoint and read back on resume, so its keys must stay
 * stable: tool_call_id, name, arguments, requires_approval, access, kind,
 * label, activity_label, is_builtin, icon_url, brand_color, reason.
 */
class ToolLoopRunner
{
    public const APPROVAL_REASON = 'This action can make changes, so it needs your approval before it runs.';

    public const DENIED_RESULT = 'The user reviewed this action and chose not to approve it. Acknowledge their decision briefly and continue helping without running it; do not call this tool again unless they ask.';

    public const RATE_LIMITED_RESULT = 'This tool is temporarily rate-limited for this agent and was not run. Do not retry it now; continue helping the user without it.';

    public const UNKNOWN_TOOL_RESULT = 'Tool error: "%s" is not one of the tools available to you right now, so it was not run. Use only the tools you were given, or answer without a tool.';

    public function __construct(
        private readonly StreamTransformer $transformer,
        private readonly ToolDispatcherInterface $dispatcher,
        private readonly ToolDisplayResolver $displayResolver,
        private readonly ToolAccessPolicy $accessPolicy,
        private readonly ToolApprovalStore $approvalStore,
        private readonly ConnectorCatalog $catalog,
    ) {}

    /**
     * Run the tool loop. Returns true when paused for approval (the checkpoint
     * + `awaiting_approval`/`done` events were already emitted) and false when
     * a final answer was streamed or the iteration cap was hit.
     *
     * When `$captureToolCallsOnly` is true (evals), the loop never dispatches a
     * tool: the first turn that emits tool calls records them on
     * {@see ChatContext::$capturedToolCalls} and stops. This lets an eval grade
     * the agent's INTENDED call without the cost or side effects of running it.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @param  array<string, mixed>  $extraSettings  per-turn settings (temperature, etc.)
     * @return Generator<int, StreamEvent, mixed, bool>
     */
    public function run(
        ChatContext $context,
        LlmProviderInterface $provider,
        string $modelName,
        string $providerSlug,
        array &$llmMessages,
        array $toolDefinitions,
        array $extraSettings = [],
        int $startIteration = 0,
        bool $captureToolCallsOnly = false,
    ): Generator {
        $maxIterations = (int) config('chatvia.agent.tools.max_iterations');
        $iteration = $startIteration;

        // The model may only call what it was offered on this turn. The names
        // gate both the transformer (no JSON sniffing without tools) and the
        // dispatch below (an invented or stale name never reaches a backend,
        // so it cannot skip the surface filter, routing or approval).
        $offeredNames = $this->offeredToolNames($toolDefinitions);
        $settings = $this->completionSettings($extraSettings, $toolDefinitions);

        while ($iteration < $maxIterations) {
            $context->offeredToolNames = $offeredNames;

            yield from $this->streamCompletion($context, $provider, $modelName, $providerSlug, $llmMessages, $settings);

            $toolCalls = $context->takeToolCalls();
            if ($toolCalls === []) {
                return false;
            }

            // Eval mode: capture the intended call(s) and stop without running
            // anything. There is no tool result to feed back, so the loop cannot
            // continue — and that's the point: an eval grades the decision, not
            // the (expensive, side-effecting) execution.
            if ($captureToolCallsOnly) {
                $this->captureToolCalls($context, $toolCalls);

                return false;
            }

            // Persist the assistant turn that requested tools, so the snapshot
            // (and the next iteration) carry it.
            $llmMessages[] = $this->assistantToolCallsMessage($context, $toolCalls);

            // Resolve display + access decision once for every offered call.
            $calls = $this->buildPendingCalls(
                $context->requestData->agent,
                $this->rejectUnofferedCalls($context, $llmMessages, $toolCalls, $offeredNames),
            );

            // If any call is gated, pause the whole turn (OpenAI needs a result
            // for every tool_call_id before the next assistant turn, so we
            // cannot half-run it). Any Deny (rate-limited) calls in the same
            // turn are resolved on resume.
            if (array_any($calls, static fn (array $call): bool => $call['requires_approval'])) {
                yield from $this->pauseForApproval($context, $llmMessages, $calls, $iteration);

                return true;
            }

            // Nothing gated → resolve every call inline (Allow runs, Deny is
            // blocked + audited).
            foreach ($calls as $pc) {
                yield from $this->resolveUngatedCall($context, $llmMessages, $pc);
            }

            // Drop the visible text buffer between iterations so the final
            // saved assistant message is only the post-tool answer.
            $context->resetStreamedMessage();
            $iteration++;
        }

        return false;
    }

    /**
     * Apply the user's approval decisions to a resumed turn's calls, appending
     * a `role: 'tool'` result for each so the loop can continue.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array<string, mixed>>  $pendingCalls
     * @param  array<string, string>  $decisions  tool_call_id => 'approve'|'deny'
     * @return Generator<int, StreamEvent>
     */
    public function applyPendingCalls(ChatContext $context, array &$llmMessages, array $pendingCalls, array $decisions): Generator
    {
        foreach ($pendingCalls as $pc) {
            $toolCallId = (string) $pc['tool_call_id'];
            $requires = (bool) ($pc['requires_approval'] ?? false);
            $decision = $decisions[$toolCallId] ?? null;

            // Rate-limited at gate time — blocked regardless of the human's call.
            if ($this->isRateLimited($pc)) {
                yield from $this->denyCall($context, $llmMessages, $pc, ToolCallOutcome::RateLimited, self::RATE_LIMITED_RESULT);

                continue;
            }

            if ($requires && $decision !== 'approve') {
                $llmMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => self::DENIED_RESULT,
                ];

                $this->emitToolCallAudit($context, $pc, ToolCallOutcome::Denied, ToolDispatchResult::text(self::DENIED_RESULT), decided: true);

                continue;
            }

            // Auto (never gated) or approved → run it for real.
            $outcome = $requires ? ToolCallOutcome::Approved : ToolCallOutcome::Auto;
            yield from $this->dispatchToolCall($context, $llmMessages, $pc, $outcome, decided: $requires);
        }
    }

    /**
     * The settings sent with every completion of the loop: the per-turn extras
     * plus the offered tools, when there are any.
     *
     * @param  array<string, mixed>  $extraSettings
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return array<string, mixed>
     */
    private function completionSettings(array $extraSettings, array $toolDefinitions): array
    {
        if ($toolDefinitions === []) {
            return $extraSettings;
        }

        return [...$extraSettings, 'tools' => $toolDefinitions, 'tool_choice' => 'auto'];
    }

    /**
     * Stream one completion through the transformer, tracking its cost.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $settings
     * @return Generator<int, StreamEvent>
     */
    private function streamCompletion(ChatContext $context, LlmProviderInterface $provider, string $modelName, string $providerSlug, array $llmMessages, array $settings): Generator
    {
        $tracker = Spectra::stream(provider: $providerSlug, model: $modelName);
        $context->spectraTracker = $tracker;

        try {
            $response = $provider->stream($modelName, $llmMessages, $settings);

            yield from $this->transformer->transform($response, $context);

            $tracker->finish();
        } catch (Throwable $e) {
            Spectra::recordFailure($tracker->getContext(), $e);
            throw $e;
        } finally {
            $context->spectraTracker = null;
        }
    }

    /**
     * @param  array<int, array{id: string, name: string, arguments: string}>  $toolCalls
     */
    private function captureToolCalls(ChatContext $context, array $toolCalls): void
    {
        foreach ($toolCalls as $tc) {
            $context->capturedToolCalls[] = ['name' => $tc['name'], 'arguments' => $tc['arguments']];
            $context->toolCallNames[] = $tc['name'];
        }
    }

    /**
     * The assistant message that requested the tools, in the OpenAI shape.
     *
     * @param  array<int, array{id: string, name: string, arguments: string}>  $toolCalls
     * @return array<string, mixed>
     */
    private function assistantToolCallsMessage(ChatContext $context, array $toolCalls): array
    {
        return [
            'role' => 'assistant',
            'content' => $context->streamedMessage,
            'tool_calls' => array_map(static fn (array $tc): array => [
                'id' => $tc['id'],
                'type' => 'function',
                'function' => [
                    'name' => $tc['name'],
                    'arguments' => $tc['arguments'],
                ],
            ], $toolCalls),
        ];
    }

    /**
     * Refuse calls to tools that were not offered: answer them with a tool
     * error right away (the model needs a result for every tool_call_id) and
     * keep them away from approval and dispatch. Returns the offered calls.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array{id: string, name: string, arguments: string}>  $toolCalls
     * @param  array<int, string>  $offeredNames
     * @return array<int, array{id: string, name: string, arguments: string}>
     */
    private function rejectUnofferedCalls(ChatContext $context, array &$llmMessages, array $toolCalls, array $offeredNames): array
    {
        $offeredCalls = [];

        foreach ($toolCalls as $tc) {
            if (in_array($tc['name'], $offeredNames, true)) {
                $offeredCalls[] = $tc;

                continue;
            }

            $this->rejectUnofferedCall($context, $llmMessages, $tc);
        }

        return $offeredCalls;
    }

    /**
     * Checkpoint the turn, surface an approval card for every gated call, and
     * end the stream until the user decides.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array<string, mixed>>  $calls
     * @return Generator<int, StreamEvent>
     */
    private function pauseForApproval(ChatContext $context, array $llmMessages, array $calls, int $iteration): Generator
    {
        $checkpoint = $this->approvalStore->persist($context, $llmMessages, $calls, $iteration);

        foreach ($calls as $pc) {
            if (! $pc['requires_approval']) {
                continue;
            }

            yield StreamEvent::tool(
                assistantMessageId: $context->assistantMessageId,
                phase: ToolCallPhase::AwaitingApproval,
                toolCallId: $pc['tool_call_id'],
                name: $pc['name'],
                args: $pc['arguments'],
                kind: $pc['kind'],
                label: $pc['label'],
                activityLabel: $pc['activity_label'] ?? null,
                iconUrl: $pc['icon_url'],
                brandColor: $pc['brand_color'],
                approvalId: $checkpoint->external_id,
                reason: $pc['reason'],
            );
        }

        yield StreamEvent::done($context->assistantMessageId, 'awaiting_approval');
    }

    /**
     * Run an ungated call, or block it when the rate limit denied it.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $pc
     * @return Generator<int, StreamEvent>
     */
    private function resolveUngatedCall(ChatContext $context, array &$llmMessages, array $pc): Generator
    {
        if ($this->isRateLimited($pc)) {
            yield from $this->denyCall($context, $llmMessages, $pc, ToolCallOutcome::RateLimited, self::RATE_LIMITED_RESULT);

            return;
        }

        yield from $this->dispatchToolCall($context, $llmMessages, $pc);
    }

    /**
     * @param  array<string, mixed>  $pc
     */
    private function isRateLimited(array $pc): bool
    {
        return ($pc['access'] ?? null) === ToolAccessDecision::Deny->value;
    }

    /**
     * Dispatch one tool call, emitting `start`/`end` events, appending the
     * `role: 'tool'` result, and recording the governance audit.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $pc
     * @param  bool  $decided  true when a human resolved a gated call (sets decided_at)
     * @return Generator<int, StreamEvent>
     */
    private function dispatchToolCall(ChatContext $context, array &$llmMessages, array $pc, ToolCallOutcome $outcome = ToolCallOutcome::Auto, bool $decided = false): Generator
    {
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: ToolCallPhase::Start,
            toolCallId: $pc['tool_call_id'],
            name: $pc['name'],
            args: $pc['arguments'],
            kind: $pc['kind'],
            label: $pc['label'],
            activityLabel: $pc['activity_label'] ?? null,
            iconUrl: $pc['icon_url'],
            brandColor: $pc['brand_color'],
            internal: $pc['is_builtin'] ?? false,
        );

        $start = microtime(true);
        $dispatched = $this->dispatcher->dispatch($context->requestData->agent, ToolCallData::named(
            $pc['name'],
            $pc['arguments'],
            $context->requestData->conversationId,
            $context->requestData->chatterExternalId,
        ));
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        $context->toolCallNames[] = $pc['name'];

        $llmMessages[] = [
            'role' => 'tool',
            'tool_call_id' => $pc['tool_call_id'],
            'content' => $dispatched->text,
        ];

        if ($dispatched->artifacts !== []) {
            $context->artifacts = array_merge($context->artifacts, $dispatched->artifacts);
        }

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: ToolCallPhase::End,
            toolCallId: $pc['tool_call_id'],
            name: $pc['name'],
            result: $dispatched->text,
            artifacts: $dispatched->artifacts !== [] ? $dispatched->artifacts : null,
        );

        $this->emitToolCallAudit($context, $pc, $outcome, $dispatched, $latencyMs, $decided);
    }

    /**
     * Resolve a blocked call without dispatching: append a recoverable
     * `role: 'tool'` result so the LLM continues, surface an `end` event, and
     * record the governance audit. Used for the `rate_limited` outcome.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $pc
     * @return Generator<int, StreamEvent>
     */
    private function denyCall(ChatContext $context, array &$llmMessages, array $pc, ToolCallOutcome $outcome, string $message): Generator
    {
        yield StreamEvent::tool(
            assistantMessageId: $context->assistantMessageId,
            phase: ToolCallPhase::End,
            toolCallId: $pc['tool_call_id'],
            name: $pc['name'],
            result: $message,
        );

        $llmMessages[] = [
            'role' => 'tool',
            'tool_call_id' => $pc['tool_call_id'],
            'content' => $message,
        ];

        $this->emitToolCallAudit($context, $pc, $outcome, ToolDispatchResult::text($message));
    }

    /**
     * Answer a call to a tool that was not offered this turn with a recoverable
     * tool error. Nothing is dispatched, no SSE event is sent (there is no real
     * tool to show) and no governance audit row is written — the audit records
     * decisions about real tools, and this name does not map to one.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array{id: string, name: string, arguments: string}  $toolCall
     */
    private function rejectUnofferedCall(ChatContext $context, array &$llmMessages, array $toolCall): void
    {
        $llmMessages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content' => sprintf(self::UNKNOWN_TOOL_RESULT, mb_substr($toolCall['name'], 0, 100)),
        ];

        Log::warning('Refused a tool call to a tool that was not offered', [
            'agent_id' => $context->requestData->agent->id,
            'tool' => mb_substr($toolCall['name'], 0, 100),
        ]);
    }

    /**
     * The function names in an OpenAI-shaped `tools` list.
     *
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return array<int, string>
     */
    private function offeredToolNames(array $toolDefinitions): array
    {
        $names = [];
        foreach ($toolDefinitions as $definition) {
            $name = $definition['function']['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Resolve display metadata + governance access decision for every tool call
     * in a turn, in the shape the checkpoint stores and the dispatch path
     * consumes.
     *
     * @param  array<int, array{id: string, name: string, arguments: string}>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    private function buildPendingCalls(Agent $agent, array $toolCalls): array
    {
        return array_map(function (array $tc) use ($agent): array {
            $name = ToolName::parse($tc['name']);
            $display = $this->displayResolver->resolve($agent, $name);
            $decision = $this->accessPolicy->decide($agent, $name);
            $requires = $decision === ToolAccessDecision::RequiresApproval;

            return [
                'tool_call_id' => $tc['id'],
                'name' => $tc['name'],
                'arguments' => $tc['arguments'],
                'requires_approval' => $requires,
                'access' => $decision->value,
                'kind' => $display->kind->value,
                'label' => $display->label,
                'activity_label' => $display->activityLabel,
                'is_builtin' => $display->isBuiltin,
                'icon_url' => $display->iconUrl,
                'brand_color' => $display->brandColor,
                'reason' => $requires ? self::APPROVAL_REASON : null,
            ];
        }, $toolCalls);
    }

    /**
     * Emit the immutable-audit event for one resolved tool call. A queued
     * listener writes the agent_activity row, so this never touches the DB on
     * the streaming hot path. Names (agent/user/connector) are denormalized here
     * so the audit survives later deletion, and the tool name is unprefixed.
     *
     * @param  array<string, mixed>  $pc
     */
    private function emitToolCallAudit(ChatContext $context, array $pc, ToolCallOutcome $outcome, ToolDispatchResult $result, ?int $latencyMs = null, bool $decided = false): void
    {
        $rd = $context->requestData;
        $agent = $rd->agent;
        $kind = ToolKind::tryFrom((string) ($pc['kind'] ?? '')) ?? ToolKind::Function;

        // No DB lookup — the connector display name comes from the already
        // resolved display label.
        $name = ToolName::parse((string) $pc['name']);
        $connectorSlug = $kind === ToolKind::Mcp ? $name->serverSlug : null;
        $toolName = $kind === ToolKind::Mcp ? $name->tool : $name->value;

        // First-party builtin capabilities (charts, forms, leads) are excluded
        // from the governance audit — they run directly and aren't logged.
        if ($connectorSlug !== null && $this->catalog->isBuiltinSlug($connectorSlug)) {
            return;
        }

        ToolCallCompleted::dispatch(
            agentId: (int) $agent->id,
            agentName: (string) $agent->name,
            workspaceId: (int) $agent->user_id,
            userId: $rd->actorUserId,
            userName: $rd->actorName,
            conversationExternalId: $rd->conversationId,
            traceId: (string) $context->assistantMessageId,
            requestId: $rd->requestId,
            toolCallId: (string) $pc['tool_call_id'],
            toolName: $toolName,
            toolKind: $kind->value,
            connectorSlug: $connectorSlug,
            // For MCP tools the display label IS the connector/server name.
            connectorName: $kind === ToolKind::Mcp ? ($pc['label'] ?? null) : null,
            arguments: (string) ($pc['arguments'] ?? ''),
            resultText: $result->text,
            isError: $result->isError,
            latencyMs: $latencyMs,
            gated: $outcome->wasGated(),
            approvalOutcome: $outcome->value,
            decidedAt: $decided ? now()->toIso8601String() : null,
        );
    }
}
