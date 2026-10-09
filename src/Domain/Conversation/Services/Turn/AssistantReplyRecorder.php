<?php

namespace Domain\Conversation\Services\Turn;

use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Enums\ConfidenceBand;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Records the assistant's answer when a turn ends: adds it to the conversation
 * (live memory now, chat log through the queue) and, on the hosted chat,
 * remembers which sources it was answered from. A turn where a model really
 * answered also announces {@see AssistantReplyCompleted}, which billing
 * charges for; a guardrail refusal is recorded for the history only.
 */
class AssistantReplyRecorder
{
    public function __construct(
        private readonly AddMessageToConversationAction $addMessage,
        private readonly ChatMemory $memory,
    ) {}

    /** A completed turn: announce it for billing, store the answer and its sources. */
    public function recordCompletedReply(ChatContext $context): void
    {
        $request = $context->requestData;

        AssistantReplyCompleted::dispatch(
            $request->agent->user_id,
            $request->message->model->id,
            $context->assistantMessageId,
            $this->documentPages($context),
        );

        $this->store($context);

        if ($request->streamSources) {
            $this->memory
                ->withScope($this->scope($context))
                ->storeSourcesForMessage($context->assistantMessageId, $context->sources->toArray());
        }
    }

    /**
     * A refused turn (an input guardrail blocked the message): kept in the
     * history, never billed — no model ran.
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
            model: $request->message->model->external_name,
            sent_at: now(),
            generation_id: $context->generationId,
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
        ));
    }

    /**
     * Whether the turn produced an answer, from deterministic retrieval
     * signals — never the LLM's own claim.
     */
    private function foundAnswer(ChatContext $context): bool
    {
        if ($context->hasQaMatch()) {
            return true;
        }

        if ($context->sources->isEmpty()) {
            return false;
        }

        return $context->confidenceScore !== null
            && $context->confidenceScore >= ConfidenceBand::MEDIUM_FLOOR;
    }

    /**
     * The summed page count of every PDF in the conversation — the same PDFs
     * that route the turn to the document LLM, so the pages are charged
     * exactly when the documents were sent. 0 for text conversations.
     */
    private function documentPages(ChatContext $context): int
    {
        $request = $context->requestData;

        if (! $request->conversationId) {
            return 0;
        }

        return (int) (Conversation::query()
            ->inSession($request->externalSessionId, $request->agent->id)
            ->where('external_id', $request->conversationId)
            ->first()
            ?->pdfAttachments()
            ->sum('pages') ?? 0);
    }

    private function scope(ChatContext $context): ChatScopeData
    {
        $request = $context->requestData;

        return new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId);
    }
}
