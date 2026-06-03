<?php

namespace Database\Factories;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_session_id' => Session::factory(),
            'source' => $this->faker->randomElement(ConversationSource::cases()),
            'title' => $this->faker->optional(0.7)->sentence(3),
        ];
    }

    /**
     * Indicate that the conversation has a specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * Indicate that the conversation has no title.
     */
    public function withoutTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => null,
        ]);
    }

    /**
     * Stamp a specific expiration on the conversation. Useful for the prune
     * command's tests to assert what gets swept and what doesn't.
     */
    public function expiresAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $expiresAt,
        ]);
    }
}
