<?php

namespace Domain\Conversation\Topic\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;
use Illuminate\Support\Facades\DB;

class CreateAgentTopicAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, User $user, string $topic, string $description): Topic
    {
        $exists = Topic::query()
            ->where('user_id', $user->id)
            ->where('topic', $topic)
            ->exists();

        if ($exists) {
            throw new DomainValidationException('topic', 'You already have a topic with this name');
        }

        return DB::transaction(function () use ($agent, $user, $topic, $description): Topic {
            $newTopic = Topic::create([
                'user_id' => $user->id,
                'topic' => $topic,
                'description' => $description,
            ]);

            $agent->topics()->attach($newTopic->id);

            return $newTopic;
        });
    }
}
