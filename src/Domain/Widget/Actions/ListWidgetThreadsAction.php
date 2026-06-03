<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Thread\WidgetThreadsResultData;

/**
 * Return the visitor's threads for an agent, auto-provisioning a default
 * "New Chat" if the session has none yet so the widget always has something
 * to render.
 */
class ListWidgetThreadsAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(Agent $agent, string $sessionExternalId): WidgetThreadsResultData
    {
        $memory = $this->memory
            ->setAgent($agent)
            ->setExternalSessionId($sessionExternalId);

        $conversations = $memory->getConversationsForCurrentSession();

        if (empty($conversations)) {
            $memory->createConversation('New Chat', ConversationSource::Widget);
            $conversations = $memory->getConversationsForCurrentSession();
        }

        return new WidgetThreadsResultData(threads: $conversations);
    }
}
