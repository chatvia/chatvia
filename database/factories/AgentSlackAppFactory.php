<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A Slack app connected to a new public agent, switched on, with a webhook
 * secret of its own.
 *
 * @extends Factory<AgentSlackApp>
 */
class AgentSlackAppFactory extends Factory
{
    #[\Override]
    protected $model = AgentSlackApp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'bot_token' => 'xoxb-'.Str::random(24),
            'signing_secret' => Str::random(32),
            'bot_user_id' => 'U0BOT',
            'team_name' => 'Acme Inc',
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
