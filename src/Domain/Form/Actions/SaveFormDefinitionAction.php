<?php

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Models\FormDefinition;
use Illuminate\Support\Str;

/**
 * Create or update a form definition for an agent. Slug is derived from the name
 * on create and kept stable on update (so rendered forms keep resolving).
 */
class SaveFormDefinitionAction
{
    public function execute(Agent $agent, FormDefinitionData $data, ?FormDefinition $existing = null): FormDefinition
    {
        $attributes = [
            'name' => $data->name,
            'subtitle' => $data->subtitle,
            'form_type' => $data->form_type,
            'schema' => array_values($data->schema),
            'lead_field_map' => $data->lead_field_map ?: null,
            'success_message' => $data->success_message,
            'agent_instructions' => $data->agent_instructions,
            'is_active' => $data->is_active,
        ];

        if ($existing instanceof FormDefinition) {
            $existing->update($attributes);

            return $existing;
        }

        return FormDefinition::query()->create([
            ...$attributes,
            'agent_id' => $agent->id,
            'slug' => $this->uniqueSlug($agent, $data->name),
        ]);
    }

    private function uniqueSlug(Agent $agent, string $name): string
    {
        $base = Str::slug($name) ?: 'form';
        $slug = $base;
        $i = 1;

        while (FormDefinition::query()->where('agent_id', $agent->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
