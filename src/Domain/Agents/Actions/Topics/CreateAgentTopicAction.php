<?php

namespace Domain\Agents\Actions\Topics;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatTopic;
use Domain\Teams\Exceptions\InviteRejectedException;
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
