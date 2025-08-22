<?php

namespace App\Http\Controllers\Chat;

use App\Http\Requests\Chat\ChatStreamRequest;
use App\Models\Llm;
use App\Services\Chat\ChatStreamService;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Chat\Data\StreamRequestMessageData;
use App\Services\Memory\ChatSession;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController
{
    public function __construct(private readonly ChatStreamService $chatStreamService, private ChatSession $session) {}

    public function __invoke(ChatStreamRequest $request, string $conversationId): StreamedResponse
    {
        return $this->chatStreamService->stream(
            new StreamRequestData(
                agent: current_agent(),
                message: new StreamRequestMessageData(
                    model: Llm::whereExternalName($request->input('model'))->first(),
                    message: $request->input('message'),
                ),
                sessionId: $this->session->firstOrCreateChatSession(),
                conversationId: $conversationId,
            ),
        );
    }
}
