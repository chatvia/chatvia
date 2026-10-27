<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Data\Turn\SourceRefData;
use Domain\Conversation\Enums\ToolApprovalDecision;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\Concerns\InjectsToolSystemNote;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Conversation\Services\Turn\CitationMarkers;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Enums\LlmFailure;
use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Events\LlmCallRefused;
use Domain\Llm\Models\Llm;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared turn envelope for every streaming source that runs the agentic tool
 * loop ({@see LlmSource}, {@see AttachmentSource}). It owns everything the
 * sources must do the same way: the `start` event, tool selection, the
 * error-to-event mapping, the partial save on a client disconnect, the
 * approval resume, and the finish step — which always runs the output
 * guardrails before the answer is saved. A subclass only says which provider
 * and model to stream from and how to word its errors.
 *
 * When the agent's guardrails hold answers ("check before showing"), the text
 * deltas are not sent while the model writes: the finished, checked answer is
 * sent in one piece, so a visitor never sees text the output guards would
 * have changed. Tool activity still shows as it happens.
 */
abstract class ToolLoopSource
{
    use InjectsToolSystemNote;

    public function __construct(
        protected readonly AssistantReplyRecorder $replyRecorder,
        protected readonly RequestDedupeStore $dedupe,
        protected readonly ToolSelector $toolSelector,
        protected readonly ToolLoopRunner $runner,
        protected readonly GuardrailInspector $guardrails,
        protected readonly GuardrailRecorder $guardrailRecorder,
        private readonly CitationMarkers $citations,
    ) {}

    /** The provider the tool loop streams this turn's completion from. */
    abstract protected function provider(ChatContext $context): ChatCompletionProvider;

    /** The provider-side model name for this turn. */
    abstract protected function modelName(ChatContext $context): string;

    /**
     * How hard the agent asked the model to reason this turn; null when the
     * model does not reason. Sent as the value the model takes
     * ({@see Llm::effortValue()}).
     */
    protected function reasoningEffort(ChatContext $context): ?ReasoningEffort
    {
        return null;
    }

    /**
     * This turn's model, when it has one: its facts decide the effort value,
     * the sampling settings and the reply length sent
     * ({@see Llm::requestSettings()}). Without one, the agent's settings go
     * as they are and no effort is sent.
     */
    protected function model(ChatContext $context): ?Llm
    {
        return null;
    }

    /** Short prefix for log lines, e.g. "Chat" or "File chat". */
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
     * The turn's candidate tools, in OpenAI shape; none for a model that
     * cannot call tools, which then answers without them. Approval is
     * communicated to the model by a single system note (see
     * injectToolSystemNote), not by annotating each tool. A turn with no
     * signed-in person gets no tool that needs approval: only a chatter or
     * staff account may approve one.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareToolDefinitions(ChatContext $context): array
    {
        if ($this->model($context)?->callsTools() === false) {
            return [];
        }

        $request = $context->requestData;

        return ToolLoopRunner::personCanApprove($context)
            ? $this->toolSelector->selectForTurn($request->agent, $context->payload->message, $request->conversationId)
            : $this->toolSelector->selectForVisitorTurn($request->agent, $context->payload->message, $request->conversationId);
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
        $effort = $this->reasoningEffort($context);

        // The output guard never sees the thinking, so it is not shown while
        // answers are held for it.
        $context->showsReasoning = $effort instanceof ReasoningEffort
            && ! $effort->isOff()
            && $context->requestData->streamReasoning
            && ! $this->holdsAnswer($context);

        $paused = yield from $this->held($context, $this->runner->run(
            context: $context,
            provider: $this->provider($context),
            modelName: $this->modelName($context),
            llmMessages: $llmMessages,
            toolDefinitions: $toolDefinitions,
            extraSettings: $this->requestSettings($context, $effort),
            startIteration: $startIteration,
        ));

        if (! $paused) {
            yield from $this->finishTurn($context);
        }
    }

    /**
     * The settings the tool loop sends: the agent's own, fitted to the model
     * (its sampling and effort value), and whether the turn shows the
     * thinking, for a provider that must ask for it.
     *
     * @return array<string, mixed>
     */
    private function requestSettings(ChatContext $context, ?ReasoningEffort $effort): array
    {
        $settings = $context->requestData->agent->llmSettings();
        $model = $this->model($context);

        if ($model instanceof Llm) {
            $settings = $model->requestSettings($settings, $effort ?? ReasoningEffort::Off);
        }

        return $context->showsReasoning ? [...$settings, AbstractLlmProvider::SHOW_REASONING => true] : $settings;
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
        } catch (ClientDisconnected|TurnStopped) {
            $this->finishCutShort($context, $rememberOnDisconnect);
        } catch (RequestException|UpstreamStreamClosed $e) {
            $this->reportRefusal($context, $e);

            yield from $this->failTurn($context, $e, $phase, ErrorCode::UpstreamUnavailable, $this->upstreamUnavailableMessage());
        } catch (Throwable $e) {
            yield from $this->failTurn($context, $e, $phase, ErrorCode::Internal, $failedMessage);
        }
    }

    /** A provider that answers as if it does not serve the turn's model may have stopped serving it. */
    private function reportRefusal(ChatContext $context, Throwable $e): void
    {
        $model = $this->model($context);

        if ($model instanceof Llm && LlmFailure::of($e) === LlmFailure::ModelUnavailable) {
            LlmCallRefused::dispatch($model->id);
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
        // A held answer was never streamed: it is sent now, in one piece.
        $corrected = $this->applyOutputGuard($context);
        // Made-up or irregular citation markers are fixed before the answer
        // is stored; the client then swaps in the fixed text too.
        $corrected = $this->citations->clean($context) ?? $corrected;

        if ($this->holdsAnswer($context)) {
            if ($context->streamedMessage !== '') {
                yield StreamEvent::delta($assistantId, $context->streamedMessage);
            }
        } elseif ($corrected !== null) {
            yield StreamEvent::replace($assistantId, $corrected);
        }

        if ($context->requestData->streamSources && $context->sources->isNotEmpty()) {
            yield StreamEvent::sources($assistantId, $this->clientSources($context));
        }

        $context->markEndTime();
        $this->screenSteps($context);
        $this->replyRecorder->recordCompletedReply($context);
        $this->rememberForReplay($context);

        yield StreamEvent::done($assistantId, 'complete', $context->getResponseTimeMs());
    }

    /**
     * The tool loop's events without the text deltas when the answer is held
     * until the output guards have checked it; else unchanged. Passes on the
     * loop's result (whether it paused for approval).
     *
     * @param  Generator<int, StreamEvent, mixed, bool>  $events
     * @return Generator<int, StreamEvent, mixed, bool>
     */
    private function held(ChatContext $context, Generator $events): Generator
    {
        if (! $this->holdsAnswer($context)) {
            return yield from $events;
        }

        foreach ($events as $event) {
            if ($event->type !== EventType::Delta) {
                yield $event;
            }
        }

        return $events->getReturn();
    }

    private function holdsAnswer(ChatContext $context): bool
    {
        return $context->requestData->guardrailsEnabled && $this->guardrails->holdsAnswers($context->requestData->agent);
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
     * Output guardrails: redact PII / replace a toxic answer (a refusal in the
     * visitor's language). Every verdict is recorded; when the text changed,
     * the streamed text is swapped for the corrected one. Returns the
     * corrected text, or null when nothing changed.
     */
    private function applyOutputGuard(ChatContext $context): ?string
    {
        $original = $context->streamedMessage;

        if ($original === '') {
            return null;
        }

        $guard = $this->guardrails->inspectOutput($context->requestData->agent, $original, $context->queryLanguage);

        if (! $guard->triggered()) {
            return null;
        }

        // Record the corrected (masked / safe) text — the audit snippet must
        // not store the raw answer's PII.
        $this->guardrailRecorder->record($context, $guard->text, $guard);

        // A guard that was only skipped leaves the answer as it is.
        if ($guard->text === $original) {
            return null;
        }

        $context->resetStreamedMessage();
        $context->appendStreamedMessage($guard->text);

        return $guard->text;
    }

    /**
     * The step chain is saved with the answer and shown again on reload, so
     * what the model wrote before its tool calls passes the output guardrails
     * too, in one check. Text the guards would change or block is dropped,
     * and so is text held back for them and never shown; the tool steps
     * stay. Without a verdict (the check failed) the text is dropped too.
     */
    private function screenSteps(ChatContext $context): void
    {
        $text = $context->stepText();

        if ($text === '') {
            return;
        }

        $kept = ! $this->holdsAnswer($context) && rescue(function () use ($context, $text): bool {
            $guard = $this->guardrails->inspectOutput($context->requestData->agent, $text, $context->queryLanguage);

            return ! $guard->blocked && $guard->text === $text;
        }, false);

        if (! $kept) {
            $context->dropTextSteps();
        }
    }

    /**
     * Save the partial answer of a turn cut short: the visitor pressed Stop,
     * or (outside the hosted chat) the browser went away. Nobody is listening
     * any more, but the text is still stored and shown in the history, so it
     * passes the same output guardrails as a full answer. A turn cut off
     * before it wrote, made or ran anything is not saved: there is nothing to
     * keep. A failure here is reported, never thrown — there is no client
     * left to tell.
     */
    private function finishCutShort(ChatContext $context, bool $rememberForReplay): void
    {
        $context->markEndTime();

        if ($context->streamedMessage === '' && $context->artifacts === [] && $context->toolCallNames === []) {
            return;
        }

        try {
            $this->applyOutputGuard($context);
        } catch (Throwable $e) {
            // Without a verdict from the guards the partial answer is not kept.
            report($e);

            return;
        }

        $this->citations->clean($context);
        $this->screenSteps($context);

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
                sources: $context->requestData->streamSources ? $this->clientSources($context) : [],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The turn's sources as the chat clients show them.
     *
     * @return list<SourceRefData>
     */
    private function clientSources(ChatContext $context): array
    {
        return RetrievedSourceData::forClients($context->sources);
    }
}
