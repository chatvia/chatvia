<?php

declare(strict_types=1);

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

/**
 * Adds an existing topic (global, or the workspace's own) to an agent.
 */
class AttachExistingTopicToAgentAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, Topic $topic): void
    {
        if ($agent->topics()->where('topics.id', $topic->id)->exists()) {
            throw new DomainValidationException('topic_id', 'Topic already associated with this agent');
        }

        $agent->topics()->attach($topic->id);
    }
}
