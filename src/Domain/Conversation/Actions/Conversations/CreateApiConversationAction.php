<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Conversations;

use App\Identity\Chatter\Models\Chatter;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Conversations\CreateApiConversationData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;

/**
 * Opens a conversation for the public API: one fresh session per
 * conversation (the API has no browser session to reuse), then the
 * conversation itself on the `api` surface. A chatter, when given, is
 * stamped on the session so replies count against their monthly limit and
 * tools see their id — attribution only, so the chatter does not need a
 * grant on the agent the way a sign-in would.
 */
class CreateApiConversationAction
{
    public function __construct(
        private readonly StartChatSessionAction $startSession,
        private readonly StartConversationAction $startConversation,
    ) {}

    public function execute(CreateApiConversationData $data): Conversation
    {
        $viewer = $data->chatter instanceof Chatter
            ? ChatViewerData::chatter($data->chatter->id)
            : null;

        $sessionId = $this->startSession->execute($data->agent, $viewer);

        return $this->startConversation->execute(
            new ChatScopeData($data->agent, $sessionId, viewer: $viewer),
            ConversationSource::Api,
            $data->title,
        );
    }
}
