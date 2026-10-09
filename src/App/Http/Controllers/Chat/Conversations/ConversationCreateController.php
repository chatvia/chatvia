<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\CreateConversationRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateConversationAction;

/**
 * Opens a hosted-chat conversation from the visitor's first message.
 */
final class ConversationCreateController
{
    /**
     * @return array{id: string}
     */
    public function __invoke(
        CreateConversationRequest $request,
        #[HostedChatAgent] Agent $agent,
        CreateConversationAction $createConversation,
        HostedChatSession $session,
    ): array {
        return [
            'id' => $createConversation->execute($request->toData($session->scope($agent))),
        ];
    }
}
