<?php

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Exceptions\InviteRejectedException;

class AttachExistingTopicToAgentAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, Topic $topic): void
    {
        if ($agent->topics()->where('chat_topics.id', $topic->id)->exists()) {
            throw new InviteRejectedException('topic_id', 'Topic already associated with this agent');
        }

        $agent->topics()->attach($topic->id);
    }
}
