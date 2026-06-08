<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\Concerns\InjectsToolSystemNote;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Spectra\Facades\Spectra;
use Throwable;

class LlmSource
{
    use InjectsToolSystemNote;

    public function __construct(
        private readonly LlmProviderInterface $llmProvider,
        private readonly ChatService $chatService,
        private readonly RequestDedupeStore $dedupe,
        private readonly ToolSelector $toolSelector,
        private readonly ToolLoopRunner $runner,
        private readonly GuardrailService $guardrails,
        private readonly GuardrailRecorder $guardrailRecorder,
    ) {}

    /**
     * Stream an LLM response using the v1 envelope.
     *
     * High-level flow:
     *   1. yield `start`
     *   2. tool loop via {@see ToolLoopRunner}: stream the LLM, relay deltas,
     *      and on tool_calls either dispatch them inline or — if any call is
     *      gated by human-in-the-loop approval — checkpoint the turn, emit
     *      `awaiting_approval`, and stop (the client resumes via resume()).
     *   3. on a final answer: yield `sources`, persist, yield `done`
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages): Generator
    {
        $assistantId = $context->assistantMessageId;

        // Bind every Spectra::stream() record produced during this turn to a
        // shared trace_id so the whole agentic chain (every tool-loop
        // iteration's upstream call) is queryable as one logical operation.
        Spectra::withTraceId($assistantId);

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
            // the runner — we don't probe the policy per candidate tool.
            if ($toolDefinitions !== []) {
                $this->injectToolSystemNote($llmMessages, (bool) config('chatvia.agent.tools.approval_enabled', true));
            }

            $model = $context->requestData->message->model;
            $paused = yield from $this->runner->run(
                context: $context,
                provider: $this->llmProvider,
                modelName: $model->internal_name,
                providerSlug: $model->provider->value,
                llmMessages: $llmMessages,
                toolDefinitions: $toolDefinitions,
                extraSettings: $agent->llmSettings(),
                startIteration: 0,
            );
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

        // Keep resumed-turn upstream calls under the same trace_id as the
        // original paused turn, so the whole approval-gated chain is one trace.
        Spectra::withTraceId($assistantId);

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
            yield from $this->runner->applyPendingCalls($context, $llmMessages, $checkpoint->pending_calls, $decisions);

            // Tools for the continuation. The approval system note already
            // rode along in the snapshot, so it is not re-injected here.
            $toolDefinitions = $this->prepareToolDefinitions($agent, $context);

            $model = $context->requestData->message->model;
            $paused = yield from $this->runner->run(
                context: $context,
                provider: $this->llmProvider,
                modelName: $model->internal_name,
                providerSlug: $model->provider->value,
                llmMessages: $llmMessages,
                toolDefinitions: $toolDefinitions,
                extraSettings: $agent->llmSettings(),
                startIteration: $checkpoint->iteration + 1,
            );
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
     * @return Generator<int, StreamEvent>
     */
    private function finishTurn(ChatContext $context): Generator
    {
        $assistantId = $context->assistantMessageId;

        // Output guardrails: redact PII / replace a toxic answer. The corrected
        // text becomes the persisted + replayed message; a `replace` event tells
        // the client to swap what it already streamed live.
        $original = $context->streamedMessage;
        if ($original !== '') {
            $guard = $this->guardrails->inspectOutput($context->requestData->agent, $original);
            if ($guard->triggered()) {
                $context->resetStreamedMessage();
                $context->appendStreamedMessage($guard->text);
                $this->guardrailRecorder->record($context, $original, $guard);
                yield StreamEvent::replace($assistantId, $guard->text);
            }
        }

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
