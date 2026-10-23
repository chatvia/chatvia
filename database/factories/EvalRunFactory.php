<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A test run of a new agent that started now and is still running, with no
 * cases yet. The agent's name and workspace are copied, as the app stores them.
 *
 * @extends Factory<EvalRun>
 */
class EvalRunFactory extends Factory
{
    #[\Override]
    protected $model = EvalRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'agent_name' => fn (array $attributes): string => (string) Agent::query()->whereKey($attributes['agent_id'])->value('name'),
            'workspace_id' => fn (array $attributes): int => (int) Agent::query()->whereKey($attributes['agent_id'])->value('workspace_id'),
            'status' => EvalRunStatus::Running,
            'total_cases' => 0,
            'started_at' => fn (): CarbonInterface => now(),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => EvalRunStatus::Completed, 'finished_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => EvalRunStatus::Failed, 'finished_at' => now()]);
    }
}
