<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An invitation to chat with a new agent, sent to a new address and valid for
 * {@see AgentChatterInvitation::TTL_DAYS} days from now.
 *
 * @extends Factory<AgentChatterInvitation>
 */
class AgentChatterInvitationFactory extends Factory
{
    #[\Override]
    protected $model = AgentChatterInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'email' => fn (): string => fake()->unique()->safeEmail(),
            'token' => fn (): string => Str::random(64),
            'expires_at' => fn () => now()->addDays(AgentChatterInvitation::TTL_DAYS),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
