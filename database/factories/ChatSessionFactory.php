<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\ChatSession;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatSession>
 */
class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'user_id' => null,
            'ip_address' => $this->faker->ipv4(),
            'country_id' => $this->faker->optional(0.8)->randomElement(
                Country::pluck('id')->toArray() ?: [null]
            ),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Indicate that the session is for a user.
     */
    public function forUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the session has a country.
     */
    public function withCountry(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => Country::inRandomOrder()->first()?->id,
        ]);
    }

    /**
     * Indicate that the session has no country.
     */
    public function withoutCountry(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => null,
        ]);
    }

    /**
     * Indicate that the session is anonymous (no user).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
