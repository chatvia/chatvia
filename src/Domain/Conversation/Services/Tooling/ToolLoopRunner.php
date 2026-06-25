<?php

namespace Domain\Conversation\Services\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Conversation\Services\Tooling\Access\ToolAccessDecision;
use Domain\Conversation\Services\Tooling\Access\ToolAccessPolicy;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Conversation\Services\Tooling\Registry\ToolDisplayResolver;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Llm\Contracts\LlmProviderInterface;
use Generator;
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
 * agent's configured provider, `MistralPdfSource` runs it on Mistral with a
 * PDF-augmented user turn. Each source owns its own start/finish envelope; the
 * runner only handles the iteration and tool plumbing.
 */
class ToolLoopRunner
{
    public const APPROVAL_REASON = 'This action can make changes, so it needs your approval before it runs.';

    public const DENIED_RESULT = 'The user reviewed this action and chose not to approve it. Acknowledge their decision briefly and continue helping without running it; do not call this tool again unless they ask.';

    public const RATE_LIMITED_RESULT = 'This tool is temporarily rate-limited for this agent and was not run. Do not retry it now; continue helping the user without it.';

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
        $agent = $context->requestData->agent;
        $assistantId = $context->assistantMessageId;
        $maxIterations = (int) config('chatvia.agent.tools.max_iterations');
        $iteration = $startIteration;

        while ($iteration < $maxIterations) {
            $settings = $extraSettings;
            if (! empty($toolDefinitions)) {
                $settings['tools'] = $toolDefinitions;
                $settings['tool_choice'] = 'auto';
            }

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

            $toolCalls = $context->takeToolCalls();
            if ($toolCalls === []) {
                return false;
            }

            // Eval mode: capture the intended call(s) and stop without running
            // anything. There is no tool result to feed back, so the loop cannot
            // continue — and that's the point: an eval grades the decision, not
            // the (expensive, side-effecting) execution.
            if ($captureToolCallsOnly) {
                foreach ($toolCalls as $tc) {
                    $context->capturedToolCalls[] = ['name' => $tc['name'], 'arguments' => $tc['arguments']];
                    $context->toolCallNames[] = $tc['name'];
                }

                return false;
            }

            // Persist the assistant turn that requested tools, so the snapshot
            // (and the next iteration) carry it.
            $llmMessages[] = [
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

            // Resolve display + access decision once for every call in the turn.
            $calls = $this->buildPendingCalls($agent, $toolCalls);
            $needsApproval = array_values(array_filter($calls, static fn (array $c): bool => $c['requires_approval']));

            // If any call is gated, pause the whole turn (OpenAI needs a result
            // for every tool_call_id before the next assistant turn, so we
            // cannot half-run it). Checkpoint, surface the cards, and stop. Any
            // Deny (rate-limited) calls in the same turn are resolved on resume.
            if ($needsApproval !== []) {
                $checkpoint = $this->approvalStore->persist($context, $llmMessages, $calls, $iteration);

                foreach ($needsApproval as $pc) {
                    yield StreamEvent::tool(
                        assistantMessageId: $assistantId,
                        phase: 'awaiting_approval',
                        toolCallId: $pc['tool_call_id'],
                        name: $pc['name'],
                        args: $pc['arguments'],
                        kind: $pc['kind'],
                        label: $pc['label'],
                        iconUrl: $pc['icon_url'],
                        brandColor: $pc['brand_color'],
                        approvalId: $checkpoint->external_id,
                        reason: $pc['reason'],
                    );
                }

                yield StreamEvent::done($assistantId, 'awaiting_approval');

                return true;
            }

            // Nothing gated → resolve every call inline (Allow runs, Deny is
            // blocked + audited).
            foreach ($calls as $pc) {
                if (($pc['access'] ?? null) === ToolAccessDecision::Deny->value) {
                    yield from $this->denyCall($context, $llmMessages, $pc, 'rate_limited', self::RATE_LIMITED_RESULT);

                    continue;
                }

                yield from $this->dispatchToolCall($context, $llmMessages, $pc);
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
            if (($pc['access'] ?? null) === ToolAccessDecision::Deny->value) {
                yield from $this->denyCall($context, $llmMessages, $pc, 'rate_limited', self::RATE_LIMITED_RESULT);

                continue;
            }

            if ($requires && $decision !== 'approve') {
                $llmMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => self::DENIED_RESULT,
                ];

                $this->emitToolCallAudit($context, $pc, 'denied', self::DENIED_RESULT, false, null, decided: true);

                continue;
            }

            // Auto (never gated) or approved → run it for real.
            $outcome = $requires ? 'approved' : 'auto';
            yield from $this->dispatchToolCall($context, $llmMessages, $pc, $outcome, decided: $requires);
        }
    }

    /**
     * Dispatch one tool call, emitting `start`/`end` events, appending the
     * `role: 'tool'` result, and recording the governance audit. `$pc` is a
     * resolved call: tool_call_id, name, arguments, kind, label, icon_url,
     * brand_color, access.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $pc
     * @param  bool  $decided  true when a human resolved a gated call (sets decided_at)
     * @return Generator<int, StreamEvent>
     */
    private function dispatchToolCall(ChatContext $context, array &$llmMessages, array $pc, string $outcome = 'auto', bool $decided = false): Generator
    {
        $agent = $context->requestData->agent;
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: 'start',
            toolCallId: $pc['tool_call_id'],
            name: $pc['name'],
            args: $pc['arguments'],
            kind: $pc['kind'],
            label: $pc['label'],
            iconUrl: $pc['icon_url'],
            brandColor: $pc['brand_color'],
        );

        $start = microtime(true);
        $dispatched = $this->dispatcher->dispatch(
            $agent,
            $pc['name'],
            $pc['arguments'],
            $context->requestData->conversationId,
        );
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
            phase: 'end',
            toolCallId: $pc['tool_call_id'],
            name: $pc['name'],
            result: $dispatched->text,
            artifacts: $dispatched->artifacts !== [] ? $dispatched->artifacts : null,
        );

        $this->emitToolCallAudit($context, $pc, $outcome, $dispatched->text, $dispatched->isError, $latencyMs, $decided);
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
    private function denyCall(ChatContext $context, array &$llmMessages, array $pc, string $outcome, string $message, bool $decided = false): Generator
    {
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: 'end',
            toolCallId: $pc['tool_call_id'],
            name: $pc['name'],
            result: $message,
        );

        $llmMessages[] = [
            'role' => 'tool',
            'tool_call_id' => $pc['tool_call_id'],
            'content' => $message,
        ];

        $this->emitToolCallAudit($context, $pc, $outcome, $message, false, null, $decided);
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
            $display = $this->displayResolver->resolve($agent, $tc['name']);
            $decision = $this->accessPolicy->decide($agent, $tc['name']);
            $requires = $decision === ToolAccessDecision::RequiresApproval;

            return [
                'tool_call_id' => $tc['id'],
                'name' => $tc['name'],
                'arguments' => $tc['arguments'],
                'requires_approval' => $requires,
                'access' => $decision->value,
                'kind' => $display['kind'],
                'label' => $display['label'],
                'icon_url' => $display['icon_url'],
                'brand_color' => $display['brand_color'],
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
    private function emitToolCallAudit(ChatContext $context, array $pc, string $outcome, ?string $resultText, bool $isError, ?int $latencyMs, bool $decided): void
    {
        $rd = $context->requestData;
        $agent = $rd->agent;
        $kind = (string) ($pc['kind'] ?? 'function');
        [$connectorSlug, $toolName] = $this->toolOrigin((string) $pc['name'], $kind);

        // First-party builtin capabilities (charts, forms, leads) are excluded
        // from the governance audit — they run directly and aren't logged.
        if ($kind === 'mcp' && $connectorSlug !== null && $this->catalog->isBuiltinSlug($connectorSlug)) {
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
            toolKind: $kind,
            connectorSlug: $connectorSlug,
            // For MCP tools the display label IS the connector/server name.
            connectorName: $kind === 'mcp' ? ($pc['label'] ?? null) : null,
            arguments: (string) ($pc['arguments'] ?? ''),
            resultText: $resultText,
            isError: $isError,
            latencyMs: $latencyMs,
            gated: in_array($outcome, ['approved', 'denied'], true),
            approvalOutcome: $outcome,
            decidedAt: $decided ? now()->toIso8601String() : null,
        );
    }

    /**
     * Split a tool name into [connector_slug, unprefixed_tool_name]. For an MCP
     * tool `mcp__{slug}__{tool}` → [slug, tool]; for a webhook function → [null,
     * name]. No DB lookup — the connector display name comes from the already
     * resolved display label.
     *
     * @return array{0: ?string, 1: string}
     */
    private function toolOrigin(string $name, string $kind): array
    {
        if ($kind !== 'mcp' || ! str_starts_with($name, 'mcp__')) {
            return [null, $name];
        }

        $parts = explode('__', substr($name, 5), 2);
        $slug = ($parts[0] ?? '') !== '' ? $parts[0] : null;
        $tool = $parts[1] ?? $name;

        return [$slug, $tool];
    }
}
