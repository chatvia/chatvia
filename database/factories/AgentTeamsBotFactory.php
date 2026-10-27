<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A Microsoft Teams bot connected to a new public agent, switched on, with a
 * webhook secret of its own.
 *
 * @extends Factory<AgentTeamsBot>
 */
class AgentTeamsBotFactory extends Factory
{
    #[\Override]
    protected $model = AgentTeamsBot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'app_id' => (string) Str::uuid7(),
            'app_secret' => Str::random(32),
            'tenant_id' => null,
            'webhook_secret' => Str::random(48),
            'is_enabled' => true,
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    /** Switched off in the agent's Channels page. */
    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }
}
