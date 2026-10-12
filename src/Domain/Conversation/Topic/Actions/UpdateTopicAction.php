<?php

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Exceptions\SystemTopicProtectedException;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

/**
 * Renames / re-describes a workspace topic. Topics belong to the workspace
 * owner (the agent's user), so any teammate who can manage the agent may edit
 * them; system topics are read-only for everyone.
 */
class UpdateTopicAction
{
    /**
     * @throws SystemTopicProtectedException when the topic is a system topic
     * @throws DomainValidationException when the topic is not the workspace's, or the name is taken
     */
    public function execute(Agent $agent, Topic $topic, string $newTopic, string $description): Topic
    {
        if ($topic->isSystem()) {
            throw new SystemTopicProtectedException('topic', 'System topics cannot be modified');
        }

        // Key to the workspace owner — topics are created with the agent's
        // workspace_id, never the acting teammate's.
        if ($topic->workspace_id !== $agent->workspace_id) {
            throw new DomainValidationException('topic', 'You can only update your own topics');
        }

        $duplicate = Topic::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('topic', $newTopic)
            ->where('id', '!=', $topic->id)
            ->exists();

        if ($duplicate) {
            throw new DomainValidationException('topic', 'You already have a topic with this name');
        }

        $topic->update(['topic' => $newTopic, 'description' => $description]);

        return $topic;
    }
}
