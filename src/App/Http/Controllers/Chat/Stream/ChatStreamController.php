<?php

namespace App\Http\Controllers\Chat\Stream;

use App\Http\Requests\Chat\ChatStreamRequest;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Memory\TurnState;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private TurnState $session,
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(ChatStreamRequest $request, string $conversationId): StreamedResponse
    {
        $agent = current_agent();

        return $this->chatStreamService->stream(
            $this->factory->forChat(
                $request,
                $agent,
                $this->session->firstOrCreateSession($agent),
                $conversationId,
            ),
        );
    }
}
