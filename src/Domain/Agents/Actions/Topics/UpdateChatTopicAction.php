<?php

namespace Domain\Agents\Actions\Topics;

use App\Auth\Models\User;
use Domain\Chat\Models\ChatTopic;
use Domain\Teams\Exceptions\InviteRejectedException;

class UpdateChatTopicAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(ChatTopic $topic, User $user, string $newTopic, string $description): ChatTopic
    {
        if ($topic->isSystem()) {
            throw new InviteRejectedException('topic', 'System topics cannot be modified');
        }

        if ($topic->user_id !== $user->id) {
            throw new InviteRejectedException('topic', 'You can only update your own topics');
        }

        $duplicate = ChatTopic::query()
            ->where('user_id', $user->id)
            ->where('topic', $newTopic)
            ->where('id', '!=', $topic->id)
            ->exists();

        if ($duplicate) {
            throw new InviteRejectedException('topic', 'You already have a topic with this name');
        }

        $topic->update(['topic' => $newTopic, 'description' => $description]);

        return $topic;
    }
}
