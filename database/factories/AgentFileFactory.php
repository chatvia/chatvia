<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SourceFile>
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
        // Use demo user instead of creating a new one
        $user = User::where('email', 'ahmad@mayahi.net')->first();

        if (! $user) {
            throw new \RuntimeException('Demo user (ahmad@mayahi.net) not found. Please run DatabaseSeeder first.');
        }

        return [
            'user_id' => $user->id,
            'agent_id' => Agent::factory(),
            'filename' => $this->faker->word().'.txt',
            'filetype' => 'pdf',
            'filepath' => 'test/files/'.$this->faker->uuid().'.txt',
            'size_in_kb' => $this->faker->numberBetween(1, 1000),
            'mime_type' => 'text/plain',
            'checksum' => $this->faker->md5(),
            'status' => 'uploaded',
            'length' => $this->faker->numberBetween(1, 50),
        ];
    }
}
