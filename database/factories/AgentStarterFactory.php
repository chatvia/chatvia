<?php

namespace Database\Factories;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentStarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentStarter>
 */
class AgentStarterFactory extends Factory
{
    protected $model = AgentStarter::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'text' => [
                fake()->sentence(),
                fake()->sentence(),
            ],
        ];
    }
}
