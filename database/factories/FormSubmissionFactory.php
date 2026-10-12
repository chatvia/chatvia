<?php

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a pending form submission that expires in six hours. Use `submitted()`
 * for a captured lead and `forDefinition()` to keep the agent in step with the
 * form.
 *
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    #[\Override]
    protected $model = FormSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_definition_id' => FormDefinition::factory(),
            // The form's own agent and its workspace.
            'agent_id' => fn (array $attributes): int => (int) FormDefinition::query()->whereKey($attributes['form_definition_id'])->value('agent_id'),
            'workspace_id' => fn (array $attributes): int => (int) Agent::query()->whereKey($attributes['agent_id'])->value('workspace_id'),
            'chat_conversation_id' => null,
            'status' => FormSubmissionStatus::Pending,
            'values' => null,
            'expires_at' => now()->addHours(6),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (array $attributes): array => ['agent_id' => $agent->id]);
    }

    public function forDefinition(FormDefinition $definition): static
    {
        return $this->state(fn (array $attributes): array => [
            'form_definition_id' => $definition->id,
            'agent_id' => $definition->agent_id,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FormSubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
