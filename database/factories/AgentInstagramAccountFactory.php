<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An Instagram professional account connected to a new public agent through
 * its Facebook Page, switched on.
 *
 * @extends Factory<AgentInstagramAccount>
 */
class AgentInstagramAccountFactory extends Factory
{
    #[\Override]
    protected $model = AgentInstagramAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'page_access_token' => 'IGAA-'.Str::random(24),
            'page_id' => fake()->unique()->numerify('##########'),
            'ig_id' => fake()->unique()->numerify('178400########'),
            'username' => 'acme.support',
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
