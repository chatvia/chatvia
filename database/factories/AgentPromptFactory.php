<?php

namespace Database\Factories;

use App\Workspace\Models\User;
use Domain\Prompt\Models\AgentPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentPrompt>
 */
class AgentPromptFactory extends Factory
{
    protected $model = AgentPrompt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'prompt' => fake()->paragraphs(2, true),
        ];
    }
}
