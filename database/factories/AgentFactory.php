<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        $team = $user->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($user);

        return [
            'external_id' => Str::random(20),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => $this->faker->words(2, true),
            'sub_domain' => $this->faker->unique()->slug(),
            'system_instructions' => 'You are a helpful assistant.',
            'is_public' => false,
        ];
    }
}
