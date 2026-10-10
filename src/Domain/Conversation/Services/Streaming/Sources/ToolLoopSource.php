<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Enums\ToolApprovalDecision;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\Concerns\AttributesSpectraTurn;
use Domain\Conversation\Services\Streaming\Sources\Concerns\InjectsToolSystemNote;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Usage\TokenUsageContext;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared turn envelope for every streaming source that runs the agentic tool
 * loop ({@see LlmSource}, {@see PdfSource}). It owns everything the
 * sources must do the same way: the `start` event, tool selection, the
 * error-to-event mapping, the partial save on a client disconnect, the
 * approval resume, and the finish step — which always runs the output
 * guardrails before the answer is saved. A subclass only says which provider
 * and model to stream from and how to word its errors.
 */
abstract class ToolLoopSource
{
    use AttributesSpectraTurn, InjectsToolSystemNote;

    public function __construct(
        protected readonly AssistantReplyRecorder $replyRecorder,
        protected readonly RequestDedupeStore $dedupe,
        protected readonly ToolSelector $toolSelector,
        protected readonly ToolLoopRunner $runner,
        protected readonly GuardrailService $guardrails,
        protected readonly GuardrailRecorder $guardrailRecorder,
        protected readonly TokenUsageContext $tokenUsage,
    ) {}

    /** The provider the tool loop streams the completion from. */
    abstract protected function provider(): LlmProviderInterface;

    /** The provider-side model name for this turn. */
    abstract protected function modelName(ChatContext $context): string;

    /** The provider slug used for cost tracking. */
    abstract protected function providerSlug(ChatContext $context): string;

    /** Short prefix for log lines, e.g. "Chat" or "PDF chat". */
    abstract protected function logLabel(): string;

    /** User-facing error when the upstream provider cannot be reached. */
    abstract protected function upstreamUnavailableMessage(): string;

    /** User-facing error when a fresh turn fails for any other reason. */
    abstract protected function turnFailedMessage(): string;

    /**
     * Extra fields for the upstream-error log line. Empty by default.
     *
     * @return array<string, mixed>
     */
    protected function upstreamLogContext(Throwable $e): array
    {
        return [];
    }

    /**
     * Stream a fresh turn using the v1 envelope:
     *   1. yield `start`
     *   2. tool loop via {@see ToolLoopRunner}: stream the model, relay deltas,
     *      and on tool calls either dispatch them inline or — if any call is
     *      gated by human approval — checkpoint the turn, emit
     *      `awaiting_approval`, and stop (the client resumes via resume()).
     *   3. on a final answer: output guardrails, `sources`, persist, `done`
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<int, StreamEvent>
     */
    protected function streamTurn(ChatContext $context, array $llmMessages): Generator
    {
        // Every Spectra record of this turn shares one trace (the whole
        // agentic chain is one operation) and the owning workspace, so
        // anonymous widget turns are attributed too.
        $this->bindSpectraTurn($context);

        yield StreamEvent::start($context->assistantMessageId, [
            'assistant_message_id' => $context->assistantMessageId,
            'user_message_id' => $context->userMessageId,
            // Masked user text (PII redacted) so the client can swap the raw
            // message it rendered optimistically — mirrors the answer `replace`.
            'user_message' => $context->payload->message,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
        ]);

        // A quick retry with the same request id replays a partial answer
        // instead of paying for a second upstream call.
        yield from $this->guarded($context, $this->freshTurn($context, $llmMessages), 'stream', $this->turnFailedMessage(), rememberOnDisconnect: true);
    }

    /**
     * Resume a turn paused for approval after the person approved or denied
     * the gated tool calls. Applies the decisions, then continues the same
     * tool loop on the same provider under the original assistant message id.
     *
     * @param  array<string, ToolApprovalDecision>  $decisions  keyed by tool_call_id
     * @return Generator<int, StreamEvent>
     */
    public function resume(ChatContext $context, ToolApprovalCheckpoint $checkpoint, array $decisions): Generator
    {
        // Same trace and workspace as the paused turn, so the whole
        // approval-gated chain is one trace.
        $this->bindSpectraTurn($context);

        yield StreamEvent::start($context->assistantMessageId, [
            'assistant_message_id' => $context->assistantMessageId,
            'user_message_id' => null,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
            'resumed' => true,
        ]);

        yield from $this->guarded(
            $context,
            $this->resumedTurn($context, $checkpoint, $decisions),
            'resume',
            __('Something went wrong while continuing the response.'),
            rememberOnDisconnect: false,
        );
    }

    /**
     * The turn's candidate tools, in OpenAI shape. Approval is communicated to
     * the model by a single system note (see injectToolSystemNote), not by
     * annotating each tool — so this stays a pure pass-through with no
     * per-candidate-tool policy lookups.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareToolDefinitions(ChatContext $context): array
    {
        return $this->toolSelector->selectForTurn(
            $context->requestData->agent,
            $context->payload->message,
            $context->requestData->conversationId,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<int, StreamEvent>
     */
    private function freshTurn(ChatContext $context, array $llmMessages): Generator
    {
        $toolDefinitions = $this->prepareToolDefinitions($context);

        // With tools, add one system note: a guard against claiming an action
        // succeeded without a real tool result, plus (when approval is on) a
        // heads-up that the app shows the Approve / Deny prompt itself. The
        // per-call gating decision is still made in the runner.
        if ($toolDefinitions !== []) {
            $this->injectToolSystemNote($llmMessages, (bool) config('chatvia.agent.tools.approval_enabled', true));
        }

        yield from $this->loopThenFinish($context, $llmMessages, $toolDefinitions, startIteration: 0);
    }

    /**
     * @param  array<string, ToolApprovalDecision>  $decisions
     * @return Generator<int, StreamEvent>
     */
    private function resumedTurn(ChatContext $context, ToolApprovalCheckpoint $checkpoint, array $decisions): Generator
    {
        // The snapshot already carries the provider-specific user turn (e.g.
        // PDF chunks) and the tool system note, so neither is added again.
        $llmMessages = $context->llmMessages;

        // Approved and never-gated calls run for real; denied ones get a
        // synthetic result so the turn stays valid and the model continues.
        yield from $this->runner->applyPendingCalls($context, $llmMessages, $checkpoint->pendingToolCalls(), $decisions);

        yield from $this->loopThenFinish($context, $llmMessages, $this->prepareToolDefinitions($context), $checkpoint->iteration + 1);
    }

    /**
     * Run the tool loop and, unless it paused for approval, finish the turn.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return Generator<int, StreamEvent>
     */
    private function loopThenFinish(ChatContext $context, array $llmMessages, array $toolDefinitions, int $startIteration): Generator
    {
        $paused = yield from $this->runner->run(
            context: $context,
            provider: $this->provider(),
            modelName: $this->modelName($context),
            providerSlug: $this->providerSlug($context),
            llmMessages: $llmMessages,
            toolDefinitions: $toolDefinitions,
            extraSettings: $context->requestData->agent->llmSettings(),
            startIteration: $startIteration,
        );

        if (! $paused) {
            yield from $this->finishTurn($context);
        }
    }

    /**
     * Turn whatever goes wrong while streaming into the matching outcome: a
     * client that went away keeps its partial answer, an unreachable provider
     * and any other failure end the stream with a typed error event.
     *
     * @param  Generator<int, StreamEvent>  $work
     * @return Generator<int, StreamEvent>
     */
    private function guarded(ChatContext $context, Generator $work, string $phase, string $failedMessage, bool $rememberOnDisconnect): Generator
    {
        try {
            yield from $work;
        } catch (ClientDisconnected) {
            $this->finishAfterDisconnect($context, $rememberOnDisconnect);
        } catch (RequestException|UpstreamStreamClosed $e) {
            yield from $this->failTurn($context, $e, $phase, ErrorCode::UpstreamUnavailable, $this->upstreamUnavailableMessage());
        } catch (Throwable $e) {
            yield from $this->failTurn($context, $e, $phase, ErrorCode::Internal, $failedMessage);
        }
    }

    /**
     * Close a turn that produced a final answer. Output guardrails always run
     * first, so no source can save or replay an answer the guards would have
     * redacted or replaced.
     *
     * @return Generator<int, StreamEvent>
     */
    protected function finishTurn(ChatContext $context): Generator
    {
        $assistantId = $context->assistantMessageId;

        // The corrected text becomes the persisted + replayed message; a
        // `replace` event tells the client to swap what it already streamed.
        $corrected = $this->applyOutputGuard($context);

        if ($corrected !== null) {
            yield StreamEvent::replace($assistantId, $corrected);
        }

        if ($context->requestData->streamSources && $context->sources->isNotEmpty()) {
            yield StreamEvent::sources($assistantId, $context->sources->toArray());
        }

        $context->markEndTime();
        $this->replyRecorder->recordCompletedReply($context);
        $this->rememberForReplay($context);

        yield StreamEvent::done($assistantId, 'complete', $context->getResponseTimeMs());
    }

    /**
     * Log and report a failed turn, and tell the client with a typed error
     * event.
     *
     * @return Generator<int, StreamEvent>
     */
    private function failTurn(ChatContext $context, Throwable $e, string $phase, ErrorCode $code, string $message): Generator
    {
        $upstream = $code === ErrorCode::UpstreamUnavailable;

        Log::error(sprintf('%s %s %s', $this->logLabel(), $phase, $upstream ? 'upstream error' : 'crashed'), [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            ...($upstream ? $this->upstreamLogContext($e) : []),
        ]);
        report($e);

        yield StreamEvent::error($context->assistantMessageId, $code, $message);
        yield StreamEvent::done($context->assistantMessageId, 'error');
    }

    /**
     * Output guardrails: redact PII / replace a toxic answer. When a guard
     * fires, the streamed text is swapped for the corrected one and the event
     * is recorded. Returns the corrected text, or null when nothing changed.
     */
    private function applyOutputGuard(ChatContext $context): ?string
    {
        $original = $context->streamedMessage;

        if ($original === '') {
            return null;
        }

        // The output check runs outside the context-build tracking scope, so
        // it opens its own to attribute its tokens to the workspace.
        $guard = $this->tokenUsage->track(
            $context->requestData->agent->user_id,
            fn (): GuardResultData => $this->guardrails->inspectOutput($context->requestData->agent, $original),
        );

        if (! $guard->triggered()) {
            return null;
        }

        $context->resetStreamedMessage();
        $context->appendStreamedMessage($guard->text);
        // Record the corrected (masked / safe) text — the audit snippet must
        // not store the raw answer's PII.
        $this->guardrailRecorder->record($context, $guard->text, $guard);

        return $guard->text;
    }

    /**
     * Save the partial answer after the client went away. Nobody is listening
     * any more, but the text is still stored and shown in the history, so it
     * passes the same output guardrails as a full answer. A failure here is
     * reported, never thrown — there is no client left to tell.
     */
    private function finishAfterDisconnect(ChatContext $context, bool $rememberForReplay): void
    {
        $context->markEndTime();

        try {
            $this->applyOutputGuard($context);
        } catch (Throwable $e) {
            // Without a verdict from the guards the partial answer is not kept.
            report($e);

            return;
        }

        try {
            $this->replyRecorder->recordCompletedReply($context);
        } catch (Throwable $e) {
            report($e);
        }

        if ($rememberForReplay) {
            $this->rememberForReplay($context);
        }
    }

    private function rememberForReplay(ChatContext $context): void
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
