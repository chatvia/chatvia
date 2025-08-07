<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentFile>
 */
class AgentFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'agent_id' => Agent::factory(),
            'name' => $this->faker->word() . '.txt',
            'filepath' => 'test/files/' . $this->faker->uuid() . '.txt',
            'size_in_kb' => $this->faker->numberBetween(1, 1000),
            'mime_type' => 'text/plain',
            'md5_hash' => $this->faker->md5(),
            'status' => 'uploaded',
            'pages' => $this->faker->numberBetween(1, 50),
        ];
    }
}
