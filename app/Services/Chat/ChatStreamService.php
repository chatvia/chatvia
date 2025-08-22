<?php

namespace App\Services\Chat;

use App\Services\Chat\Data\StreamRequestData;
use App\Support\OpenRouter;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private StreamHandler $streamHandler,
        private OpenRouter $openRouter
    ) {}

    public function stream(StreamRequestData $data): StreamedResponse
    {
        // 1. Initialize chat context
        $context = $this->chatService->initializeChatContext($data);

        // 2. Enrich context with knowledge base if applicable
        $context = $this->chatService->enrichContextWithKnowledge($context);

        // 3. Prepare LLM messages
        $llmMessages = $this->chatService->prepareLlmMessages($context);

        // 4. Create and return the stream response
        return $this->createStreamResponse($context, $llmMessages);
    }

    /**
     * Create the streamed response.
     */
    private function createStreamResponse(ChatContext $context, array $llmMessages): StreamedResponse
    {
        return $this->streamHandler->createStreamResponse(
            context: $context,
            llmMessages: $llmMessages,
            openRouter: $this->openRouter,
            onComplete: fn ($ctx) => $this->chatService->handleChatCompletion($ctx),
            onError: fn ($ctx, $e) => $this->chatService->handleClientDisconnection($ctx)
        );
    }
}
