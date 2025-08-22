<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Memory\ChatMemory;
use App\Support\KnowledgeBase;
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
            'model' => $data->message->model->internal_name,
            'message' => $data->message->message,
            'updated_message_info' => [],
            'message_for_llm' => $query,
            'language' => $lang,
        ]);

        $llmMessages = $this->memory->getLlmMessages();
        $userMessageId = null;

        // Add user message if not the first conversation
        if ($data->saveUserMessageImmediately || count($llmMessages) > 1) {
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
            assistantMessageId: Str::uuid()->toString(),
            queryLanguage: $lang,
        );
    }

    public function prepareLlmMessages(ChatContext $context): array
    {
        return $this->messageProcessor->prepareLlmMessages(
            $context->requestData->agent,
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

        return $enrichedContext;
    }

    public function handleChatCompletion(ChatContext $context): void
    {
        if (! $context->requestData->persistMessage) {
            return;
        }

        $this->saveAssistantMessage($context);

        // TODO: it does not update the conversation's title
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
        $this
            ->memory
            ->setAgent($context->requestData->agent)
            ->setSessionId($context->requestData->sessionId)
            ->setConversationId($context->requestData->conversationId)
            ->addMessageViaPayload(
                new ChatOutputPayload(
                    message_id: $context->assistantMessageId,
                    role: 'assistant',
                    message: $context->streamedMessage,
                    model: $context->requestData->message->model->external_name,
                    generation_id: $context->generationId,
                    response_time_ms: $context->getResponseTimeMs(),
                    language: $context->queryLanguage,
                )
            );
    }
}
