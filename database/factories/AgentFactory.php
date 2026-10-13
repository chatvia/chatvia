<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a private agent, in a new workspace unless `workspace_id` is given
 * (or {@see forOwner()}). The team defaults to the workspace's first team,
 * created on the fly when it has none, so every agent satisfies the NOT NULL
 * `team_id`.
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
            // Lazy: only creates a workspace when the caller doesn't pass one,
            // so seeders and tests that supply workspace_id leave no orphans.
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'team_id' => function (array $attributes): int {
                $workspace = Workspace::query()->findOrFail($attributes['workspace_id']);
                $team = $workspace->teams()->oldest('id')->first();

                return ($team ?? app(TeamService::class)->createDefaultTeam($workspace->owner ?? throw new \LogicException('A workspace always has an owner.')))->id;
            },
            'name' => fake()->words(2, true),
            // Exactly three words: a variable word count can pass the 63-character
            // limit of a chat slug (AgentSlug) and fail validation at random.
            'slug' => fake()->unique()->slug(3, false),
            'system_instructions' => 'You are a helpful assistant.',
            'is_public' => false,
        ];
    }

    /** An agent of the given owner's (or teammate's) workspace. */
    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => ['workspace_id' => $user->workspace()->id]);
    }
}
