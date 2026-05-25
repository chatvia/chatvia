<?php

namespace Domain\Chat\Services;

use App\Billing\Jobs\UpdateUserCreditsJob;
use App\LLM\Operations\QueryRewriter;
use Carbon\Carbon;
use Domain\Chat\Data\ChatInputPayload;
use Domain\Chat\Data\ChatOutputPayload;
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
            $this
                ->memory
                ->setAgent($context->requestData->agent)
                ->setExternalSessionId($context->requestData->externalSessionId)
                ->setConversationId($context->requestData->conversationId)
                ->updateConversationTitleFromMessages();
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
        $strip = '<ShowLeadForm/>';

        $message = str_replace($strip, '', $context->streamedMessage);

        // Check if the response contains the answer_not_found tag
        $answerFound = ! str_contains($message, '<NoAnswer/>');

        // Remove the answer_not_found tags from the final message
        $message = str_replace(['<NoAnswer/>'], '', $message);

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
                    message: $message,
                    model: $context->requestData->message->model->external_name,
                    sent_at: now(),
                    generation_id: $context->generationId,
                    response_time_ms: $context->getResponseTimeMs(),
                    language: $context->queryLanguage,
                    confidence_score: $context->confidenceScore,
                    is_response_from_qa: $context->hasQAMatch(),
                    agent_question_answer_id: $context->qaMatch?->id,
                    source: $context->requestData->source,
                    llm_found_answer: $answerFound,
                )
            );
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
