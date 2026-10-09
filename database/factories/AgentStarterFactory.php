<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentStarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentStarter>
 */
class AgentStarterFactory extends Factory
{
    #[\Override]
    protected $model = AgentStarter::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'starters' => [
                ['text' => fake()->sentence(), 'icon' => 'ChatBubbleLeftRightIcon'],
                ['text' => fake()->sentence(), 'icon' => null],
            ],
        ];
    }
}
