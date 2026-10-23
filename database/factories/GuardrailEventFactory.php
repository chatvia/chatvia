<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Models\GuardrailEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A prompt injection a new agent's guardrails blocked in a visitor's message.
 * The agent's name and workspace are copied, as the app stores them; the
 * visitor is known only by a hashed key.
 *
 * @extends Factory<GuardrailEvent>
 */
class GuardrailEventFactory extends Factory
{
    #[\Override]
    protected $model = GuardrailEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'agent_name' => fn (array $attributes): string => (string) Agent::query()->whereKey($attributes['agent_id'])->value('name'),
            'workspace_id' => fn (array $attributes): int => (int) Agent::query()->whereKey($attributes['agent_id'])->value('workspace_id'),
            'visitor_key' => hash('sha256', 'visitor'),
            'stage' => GuardStage::Input,
            'guard_type' => GuardType::PromptInjection,
            'outcome' => GuardOutcome::Blocked,
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    /** From the visitor with this key (hashed, as the app stores it). */
    public function fromVisitor(string $visitor): static
    {
        return $this->state(['visitor_key' => hash('sha256', $visitor)]);
    }
}
