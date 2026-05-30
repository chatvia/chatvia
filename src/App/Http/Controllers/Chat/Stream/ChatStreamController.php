<?php

namespace App\Http\Controllers\Chat\Stream;

use App\Http\Requests\Chat\ChatStreamRequest;
use Domain\Chat\Services\ChatStreamService;
use Domain\Chat\Services\Memory\ChatSession;
use Domain\Chat\Services\Streaming\StreamRequestDataFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private ChatSession $session,
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(ChatStreamRequest $request, string $conversationId): StreamedResponse
    {
        $agent = current_agent();

        return $this->chatStreamService->stream(
            $this->factory->forChat(
                $request,
                $agent,
                $this->session->firstOrCreateChatSession($agent),
                $conversationId,
            ),
        );
    }
}
