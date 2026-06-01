<?php

namespace Domain\Chat\Services;

use App\Billing\Jobs\UpdateUserCreditsJob;
use App\LLM\Operations\Query\QueryRewriter;
use Carbon\Carbon;
use Domain\Chat\Data\Messages\ChatInputPayload;
use Domain\Chat\Data\Messages\ChatOutputPayload;
use Domain\Chat\Jobs\GenerateConversationTitleJob;
use Domain\Chat\Services\Data\StreamRequestData;
use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

readonly class ChatService
{
    public function __construct(
        private ChatMemory $memory,
        private MessageProcessor $messageProcessor
    ) {}

    public function initializeChatContext(StreamRequestData $data): ChatContext
    {
        $this
            ->memory
            ->setAgent($data->agent)
            ->setExternalSessionId($data->externalSessionId)
            ->setConversationId($data->conversationId);

        ['lang' => $lang, 'query' => $query] = app(QueryRewriter::class)->rewrite($data->message->message);

        $userMessageId = Str::uuid()->toString();
        $assistantMessageId = Str::uuid()->toString();

        $payload = ChatInputPayload::from([
            'external_session_id' => $data->externalSessionId,
            'external_conversation_id' => $data->conversationId,
            'external_message_id' => $userMessageId,
            'model' => $data->message->model->external_name,
            'message' => $data->message->message,
            'sent_at' => $data->message->createdAt ? Carbon::parse($data->message->createdAt) : now(),
            'updated_message_info' => [],
            'message_for_llm' => $query,
            'language' => $lang,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
            'referrer_url' => $data->referrerUrl,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
            'source' => $data->source,
        ]);

        $llmMessages = $this->memory->getLlmMessages();

        // Add user message if not the first conversation
        if ($data->saveUserMessageImmediately || count($llmMessages) > 1) {
            $this
                ->memory
                ->setAgent($data->agent)
                ->setExternalSessionId($data->externalSessionId)
                ->setConversationId($data->conversationId)
                ->addMessageViaPayload($payload);
        } else {
            // The user message was already persisted (typically by
            // CreateConversationAction for new conversations). Reuse its
            // existing id instead of the throwaway UUID we just generated,
            // otherwise the SSE `start` event tells the frontend a UUID that
            // doesn't exist anywhere — breaking regenerate/edit later when
            // the client sends that stale id back to the server.
            $cachedMessages = $this->memory->getMessagesForConversation();
            $existingUserMessage = collect($cachedMessages)
                ->last(fn (array $message): bool => $message['role'] === 'user' && $message['content'] === $data->message->message);

            if ($existingUserMessage) {
                $userMessageId = $existingUserMessage['id'];
                $payload->external_message_id = $userMessageId;
            }
        }

        return new ChatContext(
            requestData: $data,
            payload: $payload,
            llmMessages: $llmMessages,
            userMessageId: $userMessageId,
            assistantMessageId: $assistantMessageId,
            queryLanguage: $lang,
        );
    }

    public function prepareLlmMessages(ChatContext $context): array
    {
        return $this->messageProcessor->prepareLlmMessages(
            $context->requestData,
            $context->payload,
            $context->llmMessages
        );
    }

    /**
     * Update context with knowledge base sources and contextual message.
     * First checks for high-confidence Q&A match before searching chunks.
     */
    public function enrichContextWithKnowledge(ChatContext $context, array $lastMessages = []): ChatContext
    {
        // First, check for high-confidence Q&A match using ORIGINAL message (not rewritten)
        $qaMatch = $this->messageProcessor->searchQA(
            $context->requestData->agent->id,
            $context->payload->message  // Use original message for Q&A matching
        );

        if ($qaMatch) {
            // Q&A match found - skip chunk search
            $context->setQAMatch($qaMatch);
            $context->setConfidenceScore($qaMatch->confidence_score);

            return $context;
        }

        // No Q&A match - search knowledge base chunks
        $sources = $this->messageProcessor->searchKnowledgeBase(
            $context->requestData->agent,
            $context->payload,
            $lastMessages
        );

        $context->setSources($sources);

        // If we don't have sources, return the original context
        if ($sources->isEmpty()) {
            return $context;
        }

        // Calculate average confidence score from sources
        $avgConfidence = $this->calculateAverageConfidence($sources);
        $context->setConfidenceScore($avgConfidence);

        $contextualMessage = $this->messageProcessor->buildContextualUserMessage(
            $context->payload,
            $sources
        );

        $enrichedPayload = ChatInputPayload::from([
            'external_session_id' => $context->payload->external_session_id,
            'external_conversation_id' => $context->payload->external_conversation_id,
            'external_message_id' => $context->payload->external_message_id,
            'model' => $context->payload->model,
            'message' => $context->payload->message,
            'sent_at' => $context->payload->sent_at,
            'updated_message_info' => $context->payload->updated_message_info,
            'message_for_llm' => $contextualMessage,
            'language' => $context->payload->language,
            'ip_address' => $context->payload->ip_address,
            'user_agent' => $context->payload->user_agent,
            'referrer_url' => $context->payload->referrer_url,
            'page_url' => $context->payload->page_url,
            'locale' => $context->payload->locale,
            'source' => $context->payload->source,
        ]);

        $enrichedContext = new ChatContext(
            requestData: $context->requestData,
            payload: $enrichedPayload,
            llmMessages: $context->llmMessages,
            userMessageId: $context->userMessageId,
            assistantMessageId: $context->assistantMessageId,
            queryLanguage: $context->queryLanguage,
            startTime: $context->getStartTime(),
        );

        $enrichedContext->setSources($sources);
        $enrichedContext->setConfidenceScore($avgConfidence);

        return $enrichedContext;
    }

    public function handleChatCompletion(ChatContext $context): void
    {
        // Update user credits based on LLM model used
        UpdateUserCreditsJob::dispatch(
            userId: $context->requestData->agent->user_id,
            modelId: $context->requestData->message->model->id
        );

        // Always persist messages (including playground)
        $this->saveAssistantMessage($context);

        if (count($context->llmMessages) <= 2) {
            GenerateConversationTitleJob::dispatch(
                agent: $context->requestData->agent,
                externalSessionId: $context->requestData->externalSessionId,
                conversationId: $context->requestData->conversationId,
            );
        }

        if ($context->requestData->streamSources) {
            $this->memory->storeSourcesForMessage(
                $context->assistantMessageId,
                $context->sources->toArray()
            );
        }
    }

    /**
     * Legacy hook from the pre-envelope StreamHandler path. The old behavior
     * was to delete the user's message on disconnect, which was destructive
     * on flaky networks. Under the envelope path, partial state is preserved
     * via LlmSource::events()'s catch block — so this is now a no-op.
     */
    public function handleClientDisconnection(ChatContext $context): void
    {
        // Intentionally no-op. See LlmSource for the envelope-path equivalent.
    }

    private function saveAssistantMessage(ChatContext $context): void
    {
        $this
            ->memory
            ->setAgent($context->requestData->agent)
            ->setExternalSessionId($context->requestData->externalSessionId)
            ->setConversationId($context->requestData->conversationId)
            ->addMessageViaPayload(
                new ChatOutputPayload(
                    external_session_id: $context->requestData->externalSessionId,
                    external_conversation_id: $context->requestData->conversationId,
                    external_message_id: $context->assistantMessageId,
                    message: $context->streamedMessage,
                    model: $context->requestData->message->model->external_name,
                    sent_at: now(),
                    generation_id: $context->generationId,
                    response_time_ms: $context->getResponseTimeMs(),
                    language: $context->queryLanguage,
                    confidence_score: $context->confidenceScore,
                    is_response_from_qa: $context->hasQAMatch(),
                    agent_question_answer_id: $context->qaMatch?->id,
                    source: $context->requestData->source,
                    has_llm_found_answer: $this->didFindAnswer($context),
                    artifacts: $context->artifacts,
                    tool_calls: $context->toolCallNames,
                )
            );
    }

    /**
     * Decide whether this turn produced an answer, using deterministic
     * retrieval signals — never the LLM's self-report.
     *
     * Q&A hits are answers by definition. Otherwise we look at the chunk
     * search: empty sources or low average confidence (< 0.65, matching the
     * "low" bucket in ChatConversationQueryBuilder) means we have no
     * grounding to claim the LLM answered the user's question.
     */
    private function didFindAnswer(ChatContext $context): bool
    {
        if ($context->hasQAMatch()) {
            return true;
        }

        if ($context->sources->isEmpty()) {
            return false;
        }

        return $context->confidenceScore !== null
            && $context->confidenceScore >= 0.65;
    }

    /**
     * Calculate average confidence score from knowledge base sources
     */
    private function calculateAverageConfidence(Collection $sources): ?float
    {
        if ($sources->isEmpty()) {
            return null;
        }

        $confidenceScores = $sources
            ->pluck('confidence_score')
            ->filter(fn ($score): bool => $score !== null);

        if ($confidenceScores->isEmpty()) {
            return null;
        }

        return round($confidenceScores->avg(), 4);
    }
}
