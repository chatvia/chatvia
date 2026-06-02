<?php

namespace Domain\Topic\Actions;

use App\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Team\Exceptions\InviteRejectedException;
use Domain\Topic\Models\ChatTopic;
use Illuminate\Support\Facades\DB;

class CreateAgentTopicAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, User $user, string $topic, string $description): ChatTopic
    {
        $exists = ChatTopic::query()
            ->where('user_id', $user->id)
            ->where('topic', $topic)
            ->exists();

        if ($exists) {
            throw new InviteRejectedException('topic', 'You already have a topic with this name');
        }

        return DB::transaction(function () use ($agent, $user, $topic, $description): ChatTopic {
            $newTopic = ChatTopic::create([
                'user_id' => $user->id,
                'topic' => $topic,
                'description' => $description,
            ]);

            $agent->topics()->attach($newTopic->id);

            return $newTopic;
        });
    }
}
