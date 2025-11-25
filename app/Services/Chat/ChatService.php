<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Memory\ChatMemory;
use App\Services\Search\KnowledgeBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly KnowledgeBase $knowledgeBase,
        private readonly MessageProcessor $messageProcessor
    ) {}

    public function initializeChatContext(StreamRequestData $data): ChatContext
    {
        $this
            ->memory
            ->setAgent($data->agent)
            ->setSessionId($data->sessionId)
            ->setConversationId($data->conversationId);

        ['lang' => $lang, 'query' => $query] = $this->knowledgeBase->rewriteQuery($data->message->message);

        $payload = ChatInputPayload::from([
            'model' => $data->message->model->external_name,
            'message' => $data->message->message,
            'updated_message_info' => [],
            'message_for_llm' => $query,
            'language' => $lang,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
            'referrer_url' => $data->referrerUrl,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
        ]);

        $llmMessages = $this->memory->getLlmMessages();
        $userMessageId = null;

        // Add user message if not the first conversation
        if ($data->persistMessage && ($data->saveUserMessageImmediately || count($llmMessages) > 1)) {
            $userMessageId = $this
                ->memory
                ->setAgent($data->agent)
                ->setSessionId($data->sessionId)
                ->setConversationId($data->conversationId)
                ->addMessageViaPayload($payload);
        }

        return new ChatContext(
            requestData: $data,
            payload: $payload,
            llmMessages: $llmMessages,
            userMessageId: $userMessageId,
            assistantMessageId: Str::ulid()->toString(),
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
     */
    public function enrichContextWithKnowledge(ChatContext $context, array $lastMessages = []): ChatContext
    {
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
            'model' => $context->payload->model,
            'message' => $context->payload->message,
            'updated_message_info' => $context->payload->updated_message_info,
            'message_for_llm' => $contextualMessage,
            'language' => $context->payload->language,
            'ip_address' => $context->payload->ip_address,
            'user_agent' => $context->payload->user_agent,
            'referrer_url' => $context->payload->referrer_url,
            'page_url' => $context->payload->page_url,
            'locale' => $context->payload->locale,
        ]);

        $enrichedContext = new ChatContext(
            requestData: $context->requestData,
            payload: $enrichedPayload,
            llmMessages: $context->llmMessages,
            userMessageId: $context->userMessageId,
            assistantMessageId: $context->assistantMessageId,
            queryLanguage: $context->queryLanguage,
        );

        $enrichedContext->setSources($sources);
        $enrichedContext->setConfidenceScore($avgConfidence);

        return $enrichedContext;
    }

    public function handleChatCompletion(ChatContext $context): void
    {
        if (! $context->requestData->persistMessage) {
            return;
        }

        if ($context->requestData->persistMessage) {
            $this->saveAssistantMessage($context);
        }

        if (count($context->llmMessages) <= 2) {
            $this
                ->memory
                ->setAgent($context->requestData->agent)
                ->setSessionId($context->requestData->sessionId)
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

    public function handleClientDisconnection(ChatContext $context): void
    {
        if ($context->userMessageId) {
            $this->memory->removeMessage($context->userMessageId);
        }
    }

    private function saveAssistantMessage(ChatContext $context): void
    {
        $strip = '<ShowLeadForm/>';

        $message = str_replace($strip, '', $context->streamedMessage);

        $this
            ->memory
            ->setAgent($context->requestData->agent)
            ->setSessionId($context->requestData->sessionId)
            ->setConversationId($context->requestData->conversationId)
            ->addMessageViaPayload(
                new ChatOutputPayload(
                    message_id: $context->assistantMessageId,
                    role: 'assistant',
                    message: $message,
                    model: $context->requestData->message->model->external_name,
                    generation_id: $context->generationId,
                    response_time_ms: $context->getResponseTimeMs(),
                    language: $context->queryLanguage,
                    confidence_score: $context->confidenceScore,
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
            ->filter(fn ($score) => $score !== null);

        if ($confidenceScores->isEmpty()) {
            return null;
        }

        return round($confidenceScores->avg(), 4);
    }
}
