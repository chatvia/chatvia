<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Voice;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\StartVoiceCallRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Illuminate\Http\JsonResponse;

/**
 * Opens a voice call in one of the hosted-chat visitor's conversations and
 * returns the WebRTC answer for the browser. From the start page there is no
 * conversation yet, so a new one is opened for the call. A refused call is
 * answered by the exception handler (its code, status and message).
 */
readonly class StartVoiceCallController
{
    public function __construct(
        private HostedChatSession $session,
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(
        StartVoiceCallRequest $request,
        #[HostedChatAgent] Agent $agent,
        StartVoiceCallAction $start,
        StartConversationAction $startConversation,
        ?string $conversationId = null,
    ): JsonResponse {
        $conversationId ??= $startConversation->execute($this->session->scope($agent), ConversationSource::HostedChat);

        $call = $start->execute(
            $this->factory->voiceForChat($request, $agent, $this->session->findOrStart($agent), $conversationId),
            $request->sdp(),
        );

        return response()->json($call);
    }
}
