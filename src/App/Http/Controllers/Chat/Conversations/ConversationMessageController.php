<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Media\GeneratedMediaArtifacts;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;

/**
 * The history of one of the hosted-chat visitor's conversations, with each
 * message's uploaded files so the chat can show them under their bubble, and
 * each generated image or audio file as it stands now.
 */
class ConversationMessageController
{
    /**
     * @return list<MessageData>
     */
    public function __invoke(
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
        ChatMemory $memory,
        HostedChatSession $session,
        MessageAttachmentHydrator $hydrator,
        GeneratedMediaArtifacts $generatedMedia,
    ): array {
        $scope = $session->scope($agent, $conversationId);
        $memory->withScope($scope);

        abort_if(! $memory->conversationExists(), 404);

        $messages = $memory->getMessagesForConversation();

        $conversation = Conversation::query()
            ->inSession($scope->sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation === null) {
            return $messages;
        }

        return $generatedMedia->forHistory($hydrator->hydrate($messages, $conversation), $conversation);
    }
}
