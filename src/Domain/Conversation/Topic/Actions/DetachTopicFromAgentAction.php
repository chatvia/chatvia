<?php

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;

class DetachTopicFromAgentAction
{
    public function execute(Agent $agent, Topic $topic): void
    {
        $agent->topics()->detach($topic->id);

        if ($topic->isUserDefined() && $topic->agents()->count() === 0) {
            $topic->delete();
        }
    }
}
