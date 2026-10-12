<?php

namespace Domain\Conversation\Topic\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Creates a workspace topic and attaches it to the agent in one transaction.
 * The topic is owned by the workspace (the agent's user), whoever creates it.
 */
class CreateAgentTopicAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, string $topic, string $description): Topic
    {
        // Topics are a workspace resource (attached to the workspace's agents),
        // so scope uniqueness and ownership to the agent's owner — not the
        // acting employee, whose deletion would otherwise cascade the topic away.
        $exists = Topic::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('topic', $topic)
            ->exists();

        if ($exists) {
            throw new DomainValidationException('topic', 'You already have a topic with this name');
        }

        return DB::transaction(function () use ($agent, $topic, $description): Topic {
            $newTopic = Topic::create([
                'workspace_id' => $agent->workspace_id,
                'topic' => $topic,
                'description' => $description,
            ]);

            $agent->topics()->attach($newTopic->id);

            return $newTopic;
        });
    }
}
