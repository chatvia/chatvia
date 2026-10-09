<?php

namespace Domain\Widget\Queries;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Read side of the widget's conversation list: the conversations a visitor's
 * session still shows, oldest first. Threads the visitor cleared are left out
 * (the chat memory never rebuilds them), so they cannot reappear in the widget.
 *
 * Each entry is the array form of {@see ConversationData}, exactly as the chat
 * memory caches it, because the widget reads these keys straight from the JSON.
 */
class ListWidgetConversationsQuery
{
    public function __construct(private readonly ChatMemory $memory) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(Agent $agent, string $sessionExternalId): array
    {
        return $this->memory
            ->withContext($agent, $sessionExternalId)
            ->getConversationsForCurrentSession();
    }
}
