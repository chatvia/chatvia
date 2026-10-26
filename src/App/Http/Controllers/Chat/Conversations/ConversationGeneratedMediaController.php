<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;

/**
 * Every image and audio file made in one of the hosted-chat visitor's
 * conversations, as they stand now. The chat asks for it every few seconds
 * while a file is still being made and the WebSocket update has not come
 * (no connection, or it dropped). Another visitor's conversation is a 404.
 */
class ConversationGeneratedMediaController
{
    /**
     * @return list<GeneratedMediaData>
     */
    public function __invoke(
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
        HostedChatSession $session,
    ): array {
        $sessionId = $session->find($agent);

        $conversation = $sessionId !== null
            ? Conversation::query()->inSession($sessionId, $agent->id)->where('external_id', $conversationId)->first()
            : null;

        abort_unless($conversation instanceof Conversation, 404);

        return GeneratedMedia::query()
            ->where('chat_conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(GeneratedMediaData::fromModel(...))
            ->values()
            ->all();
    }
}
