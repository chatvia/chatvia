<?php

namespace Database\Factories;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a chat message (user or assistant) inside a fresh conversation.
 * Assistant messages get a model, a response time and sometimes a confidence
 * score, like real replies.
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
        $role = fake()->randomElement(['user', 'assistant']);

        return [
            'chat_conversation_id' => Conversation::factory(),
            'workspace_id' => fn (array $attributes): int => (int) Conversation::query()->whereKey($attributes['chat_conversation_id'])->value('workspace_id'),
            'generation_id' => fake()->optional(0.5)->uuid(),
            'content' => fake()->paragraphs(random_int(1, 3), true),
            'metadata' => null,
            'role' => $role,
            'llm_id' => $role === 'assistant' ? $this->getRandomLlmId() : null,
            'confidence_score' => $role === 'assistant' ? fake()->optional(0.7)->randomFloat(4, 0.7, 1) : null,
            'feedback' => fake()->optional(0.2)->randomElement(['positive', 'negative']),
            'response_time_ms' => $role === 'assistant' ? fake()->numberBetween(500, 5000) : null,
        ];
    }

    /**
     * Get a random LLM ID from the database.
     */
    private function getRandomLlmId(): ?int
    {
        $llmIds = Llm::pluck('id')->toArray();

        return $llmIds ? fake()->randomElement($llmIds) : null;
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
            'llm_id' => $this->getRandomLlmId(),
            'confidence_score' => fake()->randomFloat(4, 0.7, 1),
            'response_time_ms' => fake()->numberBetween(500, 5000),
        ]);
    }

    /**
     * Indicate that the message has positive feedback.
     */
    public function withPositiveFeedback(): static
    {
        return $this->state(fn (array $attributes): array => [
            'feedback' => 'positive',
        ]);
    }

    /**
     * Indicate that the message has negative feedback.
     */
    public function withNegativeFeedback(): static
    {
        return $this->state(fn (array $attributes): array => [
            'feedback' => 'negative',
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
