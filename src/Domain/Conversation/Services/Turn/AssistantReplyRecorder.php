<?php

namespace Domain\Conversation\Services\Turn;

use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\MessageSourceData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Context;
use Spectra\Support\Tracking\TraceContext;

/**
 * Records the assistant's answer when a turn ends: adds it to the conversation
 * (live memory now, chat log through the queue) with the knowledge it was
 * given, and, for a client that shows sources, remembers them in the live
 * memory so they come back with the history. A turn where a model really
 * answered also announces {@see AssistantReplyCompleted}, which counts against
 * the workspace's message limit; a guardrail refusal is recorded for the
 * history only.
 */
class AssistantReplyRecorder
{
    public function __construct(
        private readonly AddMessageToConversationAction $addMessage,
        private readonly ChatMemory $memory,
    ) {}

    /** A completed turn: announce it, store the answer and its sources. */
    public function recordCompletedReply(ChatContext $context): void
    {
        $request = $context->requestData;

        AssistantReplyCompleted::dispatch($request->agent->workspace_id, $context->assistantMessageId);

        $this->store($context);

        if ($request->streamSources) {
            $this->memory
                ->withScope($this->scope($context))
                ->storeSourcesForMessage($context->assistantMessageId, RetrievedSourceData::forClients($context->sources));
        }
    }

    /**
     * A refused turn (an input guardrail blocked the message): kept in the
     * history, never counted — no model ran.
     */
    public function recordRefusal(ChatContext $context): void
    {
        $this->store($context);
    }

    private function store(ChatContext $context): void
    {
        $request = $context->requestData;

        $this->addMessage->execute($this->scope($context), new AssistantTurnData(
            external_session_id: $request->externalSessionId,
            external_conversation_id: $request->conversationId,
            external_message_id: $context->assistantMessageId,
            message: $context->streamedMessage,
            llm_id: $request->message->model->id,
            sent_at: now(),
            generation_id: $context->generationId,
            trace_id: $this->traceId(),
            prompt_tokens: $context->usage['prompt_tokens'] ?? null,
            completion_tokens: $context->usage['completion_tokens'] ?? null,
            response_time_ms: $context->getResponseTimeMs(),
            language: $context->queryLanguage,
            confidence_score: $context->confidenceScore,
            is_response_from_qa: $context->hasQaMatch(),
            agent_question_answer_id: $context->qaMatch?->id,
            source: $request->source,
            has_llm_found_answer: $this->foundAnswer($context),
            artifacts: $context->artifacts,
            tool_calls: $context->toolCallNames,
            sources: $context->sources->values()->map(static fn (RetrievedSourceData $source, int $rank): MessageSourceData => MessageSourceData::fromRetrieved($source, $rank))->all(),
            voice: $request->voice,
            steps: $context->steps,
        ));
    }

    /**
     * The Spectra trace that groups this turn's AI calls, read from the hidden
     * Context entry Spectra's own watchers fill. Read, never created: an id
     * exists only when at least one AI call was recorded this turn, so a stored
     * id always has calls behind it. Null while Spectra is off (`spectra.enabled`
     * is the install-and-recording switch, see ToolRecording).
     */
    private function traceId(): ?string
    {
        if (! config('spectra.enabled')) {
            return null;
        }

        $traceId = Context::getHidden(TraceContext::TRACE_ID);

        return filled($traceId) ? $traceId : null;
    }

    /**
     * Whether the turn found an answer, from deterministic retrieval signals,
     * never the LLM's own claim: a Q&A match, or knowledge that passed a
     * relevance check. Knowledge used without any check says nothing (null);
     * no knowledge at all means not found.
     */
    private function foundAnswer(ChatContext $context): ?bool
    {
        if ($context->hasQaMatch()) {
            return true;
        }

        if ($context->sources->isEmpty()) {
            return false;
        }

        return $context->confidenceScore !== null ? true : null;
    }

    private function scope(ChatContext $context): ChatScopeData
    {
        $request = $context->requestData;

        return new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId);
    }
}
