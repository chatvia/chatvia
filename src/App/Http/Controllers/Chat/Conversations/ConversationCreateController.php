<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\CreateConversationRequest;
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
        CreateConversationAction $createConversation,
        HostedChatSession $session,
    ): array {
        $agent = current_agent();

        abort_if(! $agent, 404);

        return [
            'id' => $createConversation->execute($request->toData($session->scope($agent))),
        ];
    }
}
