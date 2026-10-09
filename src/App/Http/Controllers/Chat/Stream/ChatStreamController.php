<?php

namespace App\Http\Controllers\Chat\Stream;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\ChatStreamRequest;
use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the agent's answer to a hosted-chat message as server-sent events.
 */
readonly class ChatStreamController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private HostedChatSession $session,
        private StreamRequestDataFactory $factory,
        private SseResponder $sse,
    ) {}

    public function __invoke(ChatStreamRequest $request, string $conversationId, #[HostedChatAgent] Agent $agent): StreamedResponse
    {
        return $this->sse->respond($this->chatStreamService->stream(
            $this->factory->forChat($request, $agent, $this->session->findOrStart($agent), $conversationId),
        ));
    }
}
