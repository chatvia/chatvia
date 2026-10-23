<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The topic analysis gave a new conversation: by default a label it suggested
 * ("refunds") that no workspace topic holds yet. {@see forTopic()} files the
 * conversation under a workspace topic instead.
 *
 * @extends Factory<ConversationTopic>
 */
class ConversationTopicFactory extends Factory
{
    #[\Override]
    protected $model = ConversationTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_conversation_id' => Conversation::factory(),
            'workspace_id' => fn (array $attributes): int => (int) Conversation::query()->whereKey($attributes['chat_conversation_id'])->value('workspace_id'),
            'topic_id' => null,
            'suggested_label' => 'refunds',
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state(['chat_conversation_id' => $conversation->id, 'workspace_id' => $conversation->workspace_id]);
    }

    public function suggested(string $label): static
    {
        return $this->state(['topic_id' => null, 'suggested_label' => $label]);
    }

    public function forTopic(Topic $topic): static
    {
        return $this->state(['topic_id' => $topic->id, 'suggested_label' => null]);
    }
}
