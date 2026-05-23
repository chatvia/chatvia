<?php

namespace App\Http\Controllers\Chat;

use App\Http\Requests\Chat\ChatStreamRequest;
use App\Services\Chat\ChatStreamService;
use App\Services\Memory\ChatSession;
use App\Support\Streaming\StreamRequestDataFactory;
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
