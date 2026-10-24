<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A curated Q&A entry (one question, one answer) of a new agent unless
 * {@see attachedTo()} names one. Saving it writes its question rows, as any
 * save of an entry does. Use `AgentQuestionAnswerFactory::new()`, because
 * `AgentQuestionAnswer` does not resolve this factory by name.
 *
 * @extends Factory<AgentQuestionAnswer>
 */
class AgentQuestionAnswerFactory extends Factory
{
    #[\Override]
    protected $model = AgentQuestionAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'title' => 'Refunds',
            'answer' => 'Refunds take 5 business days.',
            'questions' => 'How long do refunds take?',
        ];
    }

    /** An entry of the agent: a Q&A entry belongs to one agent, not through `agent_sources`. */
    public function attachedTo(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }
}
