<?php

namespace Database\Factories;

use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Use demo user instead of creating a new one
        $user = User::where('email', 'ahmad@mayahi.net')->first();

        if (! $user) {
            throw new \RuntimeException('Demo user (ahmad@mayahi.net) not found. Please run DatabaseSeeder first.');
        }

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
