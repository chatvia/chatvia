<?php

namespace App\Http\Requests\Agent\Form;

use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Enums\FormType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update one of an agent's inline forms: its texts, type and the
 * field schema the chat renders (at most 30 fields).
 */
class SaveFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware (agent.permission) gates access.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'multicheckbox', 'date'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'form_type' => ['required', Rule::enum(FormType::class)],
            'success_message' => ['nullable', 'string', 'max:500'],
            'agent_instructions' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],

            'schema' => ['required', 'array', 'min:1', 'max:30'],
            'schema.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/i'],
            'schema.*.label' => ['required', 'string', 'max:120'],
            'schema.*.type' => ['required', Rule::in($types)],
            'schema.*.required' => ['boolean'],
            'schema.*.placeholder' => ['nullable', 'string', 'max:120'],
            'schema.*.options' => ['nullable', 'array'],
            'schema.*.options.*.value' => ['required_with:schema.*.options', 'string', 'max:120'],
            'schema.*.options.*.label' => ['required_with:schema.*.options', 'string', 'max:120'],

            'lead_field_map' => ['nullable', 'array'],
            'lead_field_map.*' => ['nullable', 'string', 'in:name,email,phone,job_title,company,location,message'],
        ];
    }

    public function toData(): FormDefinitionData
    {
        $validated = $this->validated();

        return new FormDefinitionData(
            external_id: null,
            slug: null,
            name: $validated['name'],
            subtitle: $validated['subtitle'] ?? null,
            form_type: $validated['form_type'],
            schema: $validated['schema'],
            lead_field_map: $validated['lead_field_map'] ?? null,
            success_message: $validated['success_message'] ?? null,
            agent_instructions: $validated['agent_instructions'] ?? null,
            is_active: $validated['is_active'] ?? true,
        );
    }
}
