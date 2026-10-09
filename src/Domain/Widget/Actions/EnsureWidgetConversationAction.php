<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Widget\Queries\ListWidgetConversationsQuery;

/**
 * Makes sure a visitor's widget session has at least one conversation. The
 * widget renders the newest thread on boot, so a session with none (a first
 * visit, or after the visitor cleared every thread) gets a fresh one.
 */
class EnsureWidgetConversationAction
{
    public function __construct(
        private readonly ListWidgetConversationsQuery $conversations,
        private readonly StartConversationAction $startConversation,
    ) {}

    /**
     * @return bool true when a new conversation was started
     */
    public function execute(Agent $agent, string $sessionExternalId): bool
    {
        if ($this->conversations->get($agent, $sessionExternalId) !== []) {
            return false;
        }

        $this->startConversation->execute(new ChatScopeData($agent, $sessionExternalId), ConversationSource::Widget);

        return true;
    }
}
