<?php

namespace Database\Factories;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a conversation inside a fresh visitor session. The denormalized
 * `agent_id` is copied from the session after making, so callers that pass only
 * a session still get a valid row.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    #[\Override]
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
            'source' => fake()->randomElement(ConversationSource::cases()),
            'title' => fake()->optional(0.7)->sentence(3),
        ];
    }

    /** Default agent_id from the session (denormalized, NOT NULL). */
    public function configure(): static
    {
        return $this->afterMaking(function (Conversation $conversation): void {
            $conversation->agent_id ??= Session::query()
                ->whereKey($conversation->chat_session_id)
                ->value('agent_id');
        });
    }

    /**
     * Indicate that the conversation has a specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => $title,
        ]);
    }

    /**
     * Indicate that the conversation has no title.
     */
    public function withoutTitle(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => null,
        ]);
    }

    /**
     * Stamp a specific expiration on the conversation. Useful for the prune
     * command's tests to assert what gets swept and what doesn't.
     */
    public function expiresAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => $expiresAt,
        ]);
    }
}
