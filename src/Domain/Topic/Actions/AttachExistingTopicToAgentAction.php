<?php

namespace Domain\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Team\Exceptions\InviteRejectedException;
use Domain\Topic\Models\ChatTopic;

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
