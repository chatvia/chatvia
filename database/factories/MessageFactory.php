<?php

namespace Database\Factories;

use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a chat message inside a fresh conversation: a visitor's message with
 * no feedback, unless a state says otherwise. An assistant reply
 * ({@see fromAssistant()}) gets the first model of the catalog, a response
 * time and a confidence score, like a real reply. Nothing is picked at random,
 * so a test that leaves a field out always runs the same case.
 *
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    #[\Override]
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_conversation_id' => Conversation::factory(),
            'workspace_id' => fn (array $attributes): int => (int) Conversation::query()->whereKey($attributes['chat_conversation_id'])->value('workspace_id'),
            'generation_id' => null,
            'content' => fake()->paragraphs(2, true),
            'metadata' => null,
            'role' => 'user',
            'llm_id' => null,
            'confidence_score' => null,
            'feedback' => null,
            'response_time_ms' => null,
        ];
    }

    /** The first model of the catalog, or none when the catalog is empty. */
    private function firstLlmId(): ?int
    {
        return Llm::query()->oldest('id')->value('id');
    }

    /**
     * Indicate that the message is from a user.
     */
    public function fromUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'user',
            'llm_id' => null,
            'confidence_score' => null,
            'response_time_ms' => null,
        ]);
    }

    /**
     * Indicate that the message is from an assistant.
     */
    public function fromAssistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'assistant',
            'llm_id' => $this->firstLlmId(),
            'confidence_score' => 0.9,
            'response_time_ms' => 1200,
        ]);
    }

    /**
     * Indicate that the message has positive feedback.
     */
    public function withPositiveFeedback(): static
    {
        return $this->state(fn (array $attributes): array => [
            'feedback' => MessageFeedback::Positive,
        ]);
    }

    /**
     * Indicate that the message has negative feedback.
     */
    public function withNegativeFeedback(): static
    {
        return $this->state(fn (array $attributes): array => [
            'feedback' => MessageFeedback::Negative,
        ]);
    }

    /**
     * Indicate that the message has metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => $metadata,
        ]);
    }
}
