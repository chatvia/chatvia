<?php

namespace Database\Factories;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;
use Support\Localization\Country\Models\Country;

/**
 * Builds an anonymous visitor session for a fresh agent, with no country
 * unless {@see withCountry()} gives it the first one of the seeded
 * `countries` table.
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
            // The agent's workspace; chat rows always carry it.
            'workspace_id' => fn (array $attributes): int => (int) Agent::query()->whereKey($attributes['agent_id'])->value('workspace_id'),
            'user_id' => null,
            'chatter_id' => null,
            'ip_address' => fake()->ipv4(),
            'country_id' => null,
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the session is for a staff account (a dashboard preview):
     * the given one, or a new one.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate that the session is for a chatter of the agent's workspace.
     */
    public function forChatter(?Chatter $chatter = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'chatter_id' => fn (array $attributes): int => $chatter?->id
                ?? Chatter::factory()->create(['workspace_id' => $attributes['workspace_id']])->id,
        ]);
    }

    /**
     * Indicate that the session has a country.
     */
    public function withCountry(): static
    {
        return $this->state(fn (array $attributes): array => [
            'country_id' => Country::query()->oldest('id')->value('id'),
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
     * Indicate that the session is anonymous (no user, no chatter).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'chatter_id' => null,
        ]);
    }
}
