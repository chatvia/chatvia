<?php

namespace Domain\Conversation\Services\Tooling;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Conversation\Services\Tooling\Registry\ToolDisplayResolver;
use Domain\Llm\Contracts\LlmProviderInterface;
use Generator;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * The provider-agnostic agentic loop. Streams an LLM completion, drains any
 * emitted tool_calls, gates them through ToolApprovalCheckpoint when needed,
 * dispatches the approved ones, and iterates until the model produces a final
 * answer or the configured iteration cap kicks in.
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

    public function __construct(
        private readonly StreamTransformer $transformer,
        private readonly ToolDispatcherInterface $dispatcher,
        private readonly ToolDisplayResolver $displayResolver,
        private readonly ToolApprovalPolicy $approvalPolicy,
        private readonly ToolApprovalStore $approvalStore,
    ) {}

    /**
     * Run the tool loop. Returns true when paused for approval (the checkpoint
     * + `awaiting_approval`/`done` events were already emitted) and false when
     * a final answer was streamed or the iteration cap was hit.
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

            // Resolve display + approval once for every call in the turn.
            $calls = $this->buildPendingCalls($agent, $toolCalls);
            $needsApproval = array_values(array_filter($calls, static fn (array $c): bool => $c['requires_approval']));

            // If any call is gated, pause the whole turn (OpenAI needs a result
            // for every tool_call_id before the next assistant turn, so we
            // cannot half-run it). Checkpoint, surface the cards, and stop.
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

            // Nothing gated → run every call inline.
            foreach ($calls as $pc) {
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

            if ($requires && $decision !== 'approve') {
                $llmMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => self::DENIED_RESULT,
                ];

                continue;
            }

            // Auto (never gated) or approved → run it for real.
            yield from $this->dispatchToolCall($context, $llmMessages, $pc);
        }
    }

    /**
     * Dispatch one tool call, emitting `start`/`end` events and appending the
     * `role: 'tool'` result. `$pc` is a resolved call: tool_call_id, name,
     * arguments, kind, label, icon_url, brand_color.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<string, mixed>  $pc
     * @return Generator<int, StreamEvent>
     */
    private function dispatchToolCall(ChatContext $context, array &$llmMessages, array $pc): Generator
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

        $dispatched = $this->dispatcher->dispatch(
            $agent,
            $pc['name'],
            $pc['arguments'],
            $context->requestData->conversationId,
        );

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
    }

    /**
     * Resolve display metadata + approval requirement for every tool call in a
     * turn, in the shape the checkpoint stores and the dispatch path consumes.
     *
     * @param  array<int, array{id: string, name: string, arguments: string}>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    private function buildPendingCalls(Agent $agent, array $toolCalls): array
    {
        return array_map(function (array $tc) use ($agent): array {
            $display = $this->displayResolver->resolve($agent, $tc['name']);
            $requires = $this->approvalPolicy->requiresApproval($agent, $tc['name']);

            return [
                'tool_call_id' => $tc['id'],
                'name' => $tc['name'],
                'arguments' => $tc['arguments'],
                'requires_approval' => $requires,
                'kind' => $display['kind'],
                'label' => $display['label'],
                'icon_url' => $display['icon_url'],
                'brand_color' => $display['brand_color'],
                'reason' => $requires ? self::APPROVAL_REASON : null,
            ];
        }, $toolCalls);
    }
}
