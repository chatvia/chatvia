<?php

declare(strict_types=1);

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;

/**
 * Removes a topic from an agent, and deletes a workspace-made topic once no
 * agent uses it any more.
 */
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
