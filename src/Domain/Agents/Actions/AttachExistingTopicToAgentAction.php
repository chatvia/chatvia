<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatTopic;
use Domain\Teams\Exceptions\InviteRejectedException;

class AttachExistingTopicToAgentAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, ChatTopic $topic): void
    {
        if ($agent->topics()->where('chat_topics.id', $topic->id)->exists()) {
            throw new InviteRejectedException('topic_id', 'Topic already associated with this agent');
        }

        $agent->topics()->attach($topic->id);
    }
}
