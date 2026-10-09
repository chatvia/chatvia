<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a private agent, with a new owner unless `user_id` is given. The team
 * defaults to the owner's primary team, created on the fly when the owner has
 * none, so every agent satisfies the NOT NULL `team_id`.
 *
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    #[\Override]
    protected $model = Agent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => Str::random(20),
            // Lazy: only creates an owner when the caller doesn't pass one, so
            // seeders and tests that supply user_id don't leave orphan users.
            'user_id' => User::factory(),
            'team_id' => function (array $attributes): int {
                $user = User::query()->findOrFail($attributes['user_id']);

                return ($user->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($user))->id;
            },
            'name' => fake()->words(2, true),
            'sub_domain' => fake()->unique()->slug(),
            'system_instructions' => 'You are a helpful assistant.',
            'is_public' => false,
        ];
    }
}
