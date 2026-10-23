<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A Facebook Page connected to a new public agent for Messenger, switched on.
 *
 * @extends Factory<AgentMessengerPage>
 */
class AgentMessengerPageFactory extends Factory
{
    #[\Override]
    protected $model = AgentMessengerPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'page_access_token' => 'EAAG-'.Str::random(24),
            'page_id' => fake()->unique()->numerify('##########'),
            'page_name' => 'Acme Support',
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
