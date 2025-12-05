<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
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
        $user = User::factory()->create();
        $team = $user->primaryTeam() ?? Team::factory()->create(['user_id' => $user->id]);

        return [
            'external_id' => Str::random(20),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => $this->faker->words(2, true),
            'sub_domain' => $this->faker->unique()->slug(),
            'system_instructions' => 'You are a helpful assistant.',
            'type' => $this->faker->randomElement(['knowledge_base', 'widget']),
            'is_public' => false,
        ];
    }
}
