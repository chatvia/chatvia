<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;
use Support\Localization\Country\Models\Country;

/**
 * Builds an anonymous visitor session for a fresh agent, usually with a country
 * picked from the seeded `countries` table.
 *
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    #[\Override]
    protected $model = Session::class;

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
            'ip_address' => fake()->ipv4(),
            'country_id' => fake()->optional(0.8)->randomElement(
                Country::pluck('id')->toArray() ?: [null]
            ),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the session is for a user.
     */
    public function forUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the session has a country.
     */
    public function withCountry(): static
    {
        return $this->state(fn (array $attributes): array => [
            'country_id' => Country::inRandomOrder()->first()?->id,
        ]);
    }

    /**
     * Indicate that the session has no country.
     */
    public function withoutCountry(): static
    {
        return $this->state(fn (array $attributes): array => [
            'country_id' => null,
        ]);
    }

    /**
     * Indicate that the session is anonymous (no user).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
