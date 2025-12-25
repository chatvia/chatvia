<?php

namespace App\Http\Controllers\Chat;

use App\Http\Requests\Chat\ChatStreamRequest;
use App\Models\Llm;
use App\Services\Chat\ChatStreamService;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Chat\Data\StreamRequestMessageData;
use App\Services\Memory\ChatSession;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamController
{
    public function __construct(private ChatStreamService $chatStreamService, private ChatSession $session) {}

    public function __invoke(ChatStreamRequest $request, string $conversationId): StreamedResponse
    {
        $agent = current_agent();

        return $this->chatStreamService->stream(
            new StreamRequestData(
                agent: $agent,
                message: new StreamRequestMessageData(
                    model: Llm::whereExternalName($request->input('model'))->first(),
                    message: $request->input('message'),
                    createdAt: $request->input('created_at'),
                ),
                sessionId: $this->session->firstOrCreateChatSession($agent),
                conversationId: $conversationId,
                saveUserMessageImmediately: $request->boolean('save_user_message_immediately'),
                streamSources: false,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                referrerUrl: $request->header('referer') ?: $request->server('HTTP_REFERER'),
                pageUrl: $request->url(),
                locale: $request->header('accept-language') ? substr($request->header('accept-language'), 0, 5) : null,
            ),
        );
    }
}
