<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A custom domain saved for a new public agent and still waiting for its
 * first check; `verified()` is one that reached the install.
 *
 * @extends Factory<AgentDomain>
 */
class AgentDomainFactory extends Factory
{
    #[\Override]
    protected $model = AgentDomain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'host' => 'help.'.Str::lower(Str::random(8)).'.example.com',
            'verification_token' => Str::random(40),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    public function verified(): static
    {
        return $this->state(['verified_at' => now(), 'last_checked_at' => now()]);
    }
}
