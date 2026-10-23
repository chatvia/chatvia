<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active test case of a new agent: the agent's answer passes when it
 * contains "5 days". The agent's name and workspace are copied, as the app
 * stores them.
 *
 * @extends Factory<EvalCase>
 */
class EvalCaseFactory extends Factory
{
    #[\Override]
    protected $model = EvalCase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'agent_name' => fn (array $attributes): string => (string) Agent::query()->whereKey($attributes['agent_id'])->value('name'),
            'workspace_id' => fn (array $attributes): int => (int) Agent::query()->whereKey($attributes['agent_id'])->value('workspace_id'),
            'name' => 'Refunds',
            'input' => 'How long do refunds take?',
            'scorer_type' => ScorerType::Contains,
            'scorer_config' => ['value' => '5 days'],
            'is_active' => true,
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
