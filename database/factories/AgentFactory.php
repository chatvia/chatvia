<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'sub_domain' => $this->faker->unique()->slug(),
            'system_instructions' => 'You are a helpful assistant.',
            'type' => $this->faker->randomElement(['knowledge', 'instruction']),
            'is_public' => false,
        ];
    }
}
