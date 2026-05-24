<?php

namespace Database\Factories;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatSession;
use App\Country\Models\Country;
use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Domain\Chat\Models\ChatSession>
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
        return $this->state(function (array $attributes) {
            $user = User::where('email', 'ahmad@mayahi.net')->first();

            if (! $user) {
                throw new \RuntimeException('Demo user (ahmad@mayahi.net) not found. Please run DatabaseSeeder first.');
            }

            return ['user_id' => $user->id];
        });
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
