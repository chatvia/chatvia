<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
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
}
