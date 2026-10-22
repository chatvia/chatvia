<?php

declare(strict_types=1);

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Removes a topic from an agent, and deletes a workspace-made topic once no
 * agent uses it any more. The topic row is locked first, so two agents
 * dropping the same topic at once can't both miss that it is now unused.
 */
class DetachTopicFromAgentAction
{
    public function execute(Agent $agent, Topic $topic): void
    {
        DB::transaction(function () use ($agent, $topic): void {
            Topic::query()->whereKey($topic->id)->lockForUpdate()->first();

            $agent->topics()->detach($topic->id);

            if ($topic->isUserDefined() && $topic->agents()->count() === 0) {
                $topic->delete();
            }
        });
    }
}
