<?php

namespace App\Http\Controllers\Chat\Stream;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\ChatStreamRequest;
use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Conversation\Services\Turn\RunningTurns;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the agent's answer to a hosted-chat message as server-sent events.
 * The turn is marked as running for its whole length ({@see RunningTurns}),
 * so a chat reloaded mid-reply waits for this answer instead of asking again.
 */
readonly class ChatStreamController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private HostedChatSession $session,
        private StreamRequestDataFactory $factory,
        private SseResponder $sse,
        private RunningTurns $runningTurns,
    ) {}

    public function __invoke(ChatStreamRequest $request, string $conversationId, #[HostedChatAgent] Agent $agent): StreamedResponse
    {
        $data = $this->factory->forChat($request, $agent, $this->session->findOrStart($agent), $conversationId);

        return $this->sse->respond($this->runningTurns->track(
            $conversationId,
            fn (): Generator => $this->chatStreamService->stream($data),
        ));
    }
}
