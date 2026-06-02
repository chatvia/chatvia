<?php

namespace Domain\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Topic\Models\ChatTopic;

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
