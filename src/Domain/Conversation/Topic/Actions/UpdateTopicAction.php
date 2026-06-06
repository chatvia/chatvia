<?php

namespace Domain\Conversation\Topic\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;

class UpdateTopicAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Topic $topic, User $user, string $newTopic, string $description): Topic
    {
        if ($topic->isSystem()) {
            throw new DomainValidationException('topic', 'System topics cannot be modified');
        }

        if ($topic->user_id !== $user->id) {
            throw new DomainValidationException('topic', 'You can only update your own topics');
        }

        $duplicate = Topic::query()
            ->where('user_id', $user->id)
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
