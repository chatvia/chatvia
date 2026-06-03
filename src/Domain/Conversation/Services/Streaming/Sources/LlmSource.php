<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformerResolver;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Conversation\Services\Tooling\Registry\ToolDisplayResolver;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Llm\Contracts\LlmProviderInterface;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmSource
{
    private const APPROVAL_REASON = 'This action can make changes, so it needs your approval before it runs.';

    private const DENIED_RESULT = 'The user reviewed this action and chose not to approve it. Acknowledge their decision briefly and continue helping without running it; do not call this tool again unless they ask.';

    public function __construct(
        private readonly LlmProviderInterface $llmProvider,
        private readonly StreamTransformerResolver $transformers,
        private readonly ChatService $chatService,
        private readonly RequestDedupeStore $dedupe,
        private readonly ToolSelector $toolSelector,
        private readonly ToolDispatcherInterface $dispatcher,
        private readonly ToolDisplayResolver $displayResolver,
        private readonly ToolApprovalPolicy $approvalPolicy,
        private readonly ToolApprovalStore $approvalStore,
    ) {}

    /**
     * Stream an LLM response using the v1 envelope.
     *
     * High-level flow:
     *   1. yield `start`
     *   2. tool loop (see runToolLoop): stream the LLM, relay deltas, and on
     *      tool_calls either dispatch them inline or — if any call is gated by
     *      human-in-the-loop approval — checkpoint the turn, emit
     *      `awaiting_approval`, and stop (the client resumes via resume()).
     *   3. on a final answer: yield `sources`, persist, yield `done`
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages): Generator
    {
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => $context->userMessageId,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
        ]);

        try {
            $agent = $context->requestData->agent;
            $toolDefinitions = $this->prepareToolDefinitions($agent, $context);

            // When the turn has tools, add one system note: a guard against
            // claiming an action succeeded without a real tool result, plus (when
            // approval is on) a heads-up that the app shows the Approve/Deny
            // prompt automatically. The per-call gating decision is still made in
            // buildPendingCalls — we don't probe the policy per candidate tool.
            if ($toolDefinitions !== []) {
                $this->injectToolSystemNote($llmMessages, (bool) config('agent.tools.approval_enabled', true));
            }

            $paused = yield from $this->runToolLoop($context, $llmMessages, $toolDefinitions, 0);
            if ($paused) {
                return;
            }

            yield from $this->finishTurn($context);
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);
            $this->rememberForReplay($context, partial: true);

            return;
        } catch (RequestException|UpstreamStreamClosed $e) {
            Log::error('Chat stream upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::UpstreamUnavailable,
                'The selected model could not be reached. Please try again.',
            );
            yield StreamEvent::done($assistantId, 'error');
        } catch (Throwable $e) {
            Log::error('Chat stream crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::Internal,
                'Something went wrong while generating the response.',
            );
            yield StreamEvent::done($assistantId, 'error');
        }
    }

    /**
     * Resume a paused turn after the user approved/denied the gated tool
     * call(s). Applies the decisions, then continues the same tool loop and
     * streams the continuation under the original assistant message id.
     *
     * @param  array<string, string>  $decisions  tool_call_id => 'approve'|'deny'
     * @return Generator<int, StreamEvent>
     */
    public function resume(ChatContext $context, ToolApprovalCheckpoint $checkpoint, array $decisions): Generator
    {
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => null,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
            'resumed' => true,
        ]);

        try {
            $agent = $context->requestData->agent;

            // The snapshot is a value array; mutate a local copy.
            $llmMessages = $context->llmMessages;

            // Apply the user's decisions to the paused turn's calls before
            // continuing — approved/auto run for real, denied get a synthetic
            // result so the turn stays valid and the model continues naturally.
            yield from $this->applyPendingCalls($context, $llmMessages, $checkpoint->pending_calls, $decisions);

            // Tools for the continuation. The approval system note already rode
            // along in the snapshot, so it is not re-injected here.
            $toolDefinitions = $this->prepareToolDefinitions($agent, $context);

            $paused = yield from $this->runToolLoop($context, $llmMessages, $toolDefinitions, $checkpoint->iteration + 1);
            if ($paused) {
                return;
            }

            yield from $this->finishTurn($context);
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);

            return;
        } catch (RequestException|UpstreamStreamClosed $e) {
            Log::error('Chat resume upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::UpstreamUnavailable,
                'The selected model could not be reached. Please try again.',
            );
            yield StreamEvent::done($assistantId, 'error');
        } catch (Throwable $e) {
            Log::error('Chat resume crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::Internal,
                'Something went wrong while continuing the response.',
            );
            yield StreamEvent::done($assistantId, 'error');
        }
    }

    /**
     * The shared agentic loop. Returns true when it paused for approval (the
     * checkpoint + `awaiting_approval`/`done` events were already emitted) and
     * false when it reached a final answer or the iteration cap.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return Generator<int, StreamEvent, mixed, bool>
     */
    private function runToolLoop(ChatContext $context, array &$llmMessages, array $toolDefinitions, int $iteration): Generator
    {
        $agent = $context->requestData->agent;
        $assistantId = $context->assistantMessageId;
        $maxIterations = (int) config('agent.tools.max_iterations');

        while ($iteration < $maxIterations) {
            $settings = $agent->llmSettings();
            if (! empty($toolDefinitions)) {
                $settings['tools'] = $toolDefinitions;
                $settings['tool_choice'] = 'auto';
            }

            $response = $this->llmProvider->stream(
                $context->requestData->message->model->internal_name,
                $llmMessages,
                $settings,
            );

            $transformer = $this->transformers->for($this->llmProvider);
            yield from $transformer->transform($response, $context);

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

            // Nothing gated → run every call inline (unchanged behaviour).
            foreach ($calls as $pc) {
                yield from $this->dispatchToolCall($context, $llmMessages, $pc);
            }

            // Drop the visible text buffer between iterations so the final saved
            // assistant message is only the post-tool answer.
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
     * @param  array<string, string>  $decisions
     * @return Generator<int, StreamEvent>
     */
    private function applyPendingCalls(ChatContext $context, array &$llmMessages, array $pendingCalls, array $decisions): Generator
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

    /**
     * The turn's candidate tools, in OpenAI shape. Approval is communicated to
     * the model by a single system note (see injectToolSystemNote), not by
     * annotating each tool — so this stays a pure pass-through with no
     * per-candidate-tool policy lookups.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepareToolDefinitions(Agent $agent, ChatContext $context): array
    {
        return $this->toolSelector->selectForTurn(
            $agent,
            $context->payload->message,
            $context->requestData->conversationId,
        );
    }

    /**
     * Append the tool-usage system note to the system message (or prepend one if
     * absent). Called once per turn when the agent has tools. The note always
     * includes the "don't claim success without a real tool result" guard, and
     * adds the approval heads-up when approval is enabled.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     */
    private function injectToolSystemNote(array &$llmMessages, bool $approvalEnabled): void
    {
        $note = trim(prompt('tools/approval', ['approval' => $approvalEnabled]));
        if ($note === '') {
            return;
        }

        foreach ($llmMessages as $i => $message) {
            if (($message['role'] ?? null) === 'system') {
                $llmMessages[$i]['content'] = rtrim((string) ($message['content'] ?? ''))."\n\n".$note;

                return;
            }
        }

        array_unshift($llmMessages, ['role' => 'system', 'content' => $note]);
    }

    /**
     * @return Generator<int, StreamEvent>
     */
    private function finishTurn(ChatContext $context): Generator
    {
        $assistantId = $context->assistantMessageId;

        if ($context->requestData->streamSources && $context->sources->isNotEmpty()) {
            yield StreamEvent::sources($assistantId, $context->sources->toArray());
        }

        $context->markEndTime();
        $this->chatService->handleChatCompletion($context);
        $this->rememberForReplay($context);

        yield StreamEvent::done(
            $assistantId,
            'complete',
            $context->getResponseTimeMs(),
        );
    }

    private function safeFinalize(ChatContext $context): void
    {
        try {
            $this->chatService->handleChatCompletion($context);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function rememberForReplay(ChatContext $context, bool $partial = false): void
    {
        $requestId = $context->requestData->requestId;
        if (! $requestId || $context->streamedMessage === '') {
            return;
        }

        try {
            $this->dedupe->remember(
                requestId: $requestId,
                assistantMessageId: $context->assistantMessageId,
                content: $context->streamedMessage,
                language: $context->queryLanguage,
                sources: $context->sources->toArray(),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
