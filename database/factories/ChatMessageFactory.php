<?php

namespace Database\Factories;

use Domain\Agents\Models\Llm;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = $this->faker->randomElement(['user', 'assistant']);

        return [
            'chat_conversation_id' => ChatConversation::factory(),
            'generation_id' => $this->faker->optional(0.5)->uuid(),
            'content' => $this->faker->paragraphs(rand(1, 3), true),
            'metadata' => null,
            'role' => $role,
            'llm_id' => $role === 'assistant' ? $this->getRandomLlmId() : null,
            'confidence_score' => $role === 'assistant' ? $this->faker->optional(0.7)->randomFloat(4, 0.7, 1) : null,
            'feedback' => $this->faker->optional(0.2)->randomElement(['positive', 'negative']),
            'response_time_ms' => $role === 'assistant' ? $this->faker->numberBetween(500, 5000) : null,
            'expires_at' => null,
        ];
    }

    /**
     * Get a random LLM ID from the database.
     */
    private function getRandomLlmId(): ?int
    {
        $llmIds = Llm::pluck('id')->toArray();

        return $llmIds ? $this->faker->randomElement($llmIds) : null;
    }

    /**
     * Indicate that the message is from a user.
     */
    public function fromUser(): static
    {
        return $this->state(fn (array $attributes) => [
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
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
            'llm_id' => $this->getRandomLlmId(),
            'confidence_score' => $this->faker->randomFloat(4, 0.7, 1),
            'response_time_ms' => $this->faker->numberBetween(500, 5000),
        ]);
    }

    /**
     * Indicate that the message has positive feedback.
     */
    public function withPositiveFeedback(): static
    {
        return $this->state(fn (array $attributes) => [
            'feedback' => 'positive',
        ]);
    }

    /**
     * Indicate that the message has negative feedback.
     */
    public function withNegativeFeedback(): static
    {
        return $this->state(fn (array $attributes) => [
            'feedback' => 'negative',
        ]);
    }

    /**
     * Indicate that the message has metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Indicate that the message expires at a specific time.
     */
    public function expiresAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $expiresAt,
        ]);
    }
}
