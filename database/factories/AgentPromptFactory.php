<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a saved prompt for the workspace Library, owned by a fresh workspace
 * user.
 *
 * @extends Factory<AgentPrompt>
 */
class AgentPromptFactory extends Factory
{
    #[\Override]
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
