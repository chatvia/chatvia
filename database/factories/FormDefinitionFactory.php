<?php

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormDefinition>
 */
class FormDefinitionFactory extends Factory
{
    protected $model = FormDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::inRandomOrder()->first()?->id ?? Agent::factory(),
            'name' => 'Contact us',
            'slug' => 'contact-'.Str::lower(Str::random(6)),
            'form_type' => FormType::Lead,
            'schema' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false],
            ],
            'lead_field_map' => ['name' => 'name', 'email' => 'email', 'message' => 'message'],
            'success_message' => 'Thanks — we will be in touch shortly.',
            'is_active' => true,
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (array $attributes): array => ['agent_id' => $agent->id]);
    }

    public function type(FormType $type): static
    {
        return $this->state(fn (array $attributes): array => ['form_type' => $type]);
    }
}
