<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A WhatsApp Business number connected to a new public agent, switched on.
 *
 * @extends Factory<AgentWhatsappNumber>
 */
class AgentWhatsappNumberFactory extends Factory
{
    #[\Override]
    protected $model = AgentWhatsappNumber::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory()->state(['is_public' => true]),
            'access_token' => 'EAAG-'.Str::random(24),
            'phone_number_id' => fake()->unique()->numerify('###############'),
            'waba_id' => fake()->unique()->numerify('###############'),
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
