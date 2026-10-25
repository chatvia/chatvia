<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A Telegram bot connected to a new public agent, switched on, with a
 * webhook secret and a header secret of its own.
 *
 * @extends Factory<AgentTelegramBot>
 */
class AgentTelegramBotFactory extends Factory
{
    #[\Override]
    protected $model = AgentTelegramBot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'bot_token' => '123456:'.Str::random(35),
            'bot_username' => 'acme_support_bot',
            'webhook_secret' => Str::random(48),
            'header_secret' => Str::random(64),
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
