<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;

/**
 * The history of one of the hosted-chat visitor's conversations, with each
 * message's uploaded files so the chat can show them under their bubble.
 */
class ConversationMessageController
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
        ChatMemory $memory,
        HostedChatSession $session,
        MessageAttachmentHydrator $hydrator,
    ): array {
        $scope = $session->scope($agent, $conversationId);
        $memory->withScope($scope);

        abort_if(! $memory->conversationExists(), 404);

        $messages = $memory->getMessagesForConversation();

        $conversation = Conversation::query()
            ->inSession($scope->sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->first();

        return $conversation !== null ? $hydrator->hydrate($messages, $conversation) : $messages;
    }
}
