<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatTopic;

class DetachTopicFromAgentAction
{
    public function execute(Agent $agent, ChatTopic $topic): void
    {
        $agent->topics()->detach($topic->id);

        if ($topic->isUserDefined() && $topic->agents()->count() === 0) {
            $topic->delete();
        }
    }
}
