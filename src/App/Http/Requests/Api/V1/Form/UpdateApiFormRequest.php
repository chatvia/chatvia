<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Form;

use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PATCH /api/v1/agents/{agent}/forms/{form}` — change an inline form. A
 * field left out keeps its current value; `schema` is replaced whole when
 * sent.
 */
class UpdateApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The form's title, shown above the rendered form.
            'name' => ['sometimes', 'string', 'max:120'],
            // Visitor-facing blurb shown under the title.
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            // What the form captures: `lead`, `booking`, `qualification` or `generic`.
            'form_type' => ['sometimes', Rule::enum(FormType::class)],
            // Shown to the visitor after a successful submission.
            'success_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Guidance for the agent on when to offer this form.
            'agent_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Whether the agent may render the form.
            'is_active' => ['sometimes', 'boolean'],

            // The full replacement field list, in order (1–30 fields).
            'schema' => ['sometimes', 'array', 'min:1', 'max:30'],
            'schema.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/i'],
            'schema.*.label' => ['required', 'string', 'max:120'],
            'schema.*.type' => ['required', Rule::in(StoreApiFormRequest::FIELD_TYPES)],
            'schema.*.required' => ['boolean'],
            'schema.*.placeholder' => ['nullable', 'string', 'max:120'],
            'schema.*.options' => ['nullable', 'array'],
            'schema.*.options.*.value' => ['required_with:schema.*.options', 'string', 'max:120'],
            'schema.*.options.*.label' => ['required_with:schema.*.options', 'string', 'max:120'],

            // Maps field keys to the standard lead fields. Null clears the map.
            'lead_field_map' => ['sometimes', 'nullable', 'array'],
            'lead_field_map.*' => ['nullable', 'string', 'in:name,email,phone,job_title,company,location,message'],
        ];
    }

    /** The form's next definition: the sent fields over the current values. */
    public function toData(FormDefinition $form): FormDefinitionData
    {
        $validated = $this->validated();
        $keep = fn (string $key, mixed $current): mixed => array_key_exists($key, $validated) ? $validated[$key] : $current;

        return new FormDefinitionData(
            external_id: null,
            slug: null,
            name: $keep('name', $form->name),
            subtitle: $keep('subtitle', $form->subtitle),
            form_type: $validated['form_type'] ?? $form->form_type->value,
            schema: $keep('schema', $form->schema),
            lead_field_map: $keep('lead_field_map', $form->lead_field_map),
            success_message: $keep('success_message', $form->success_message),
            agent_instructions: $keep('agent_instructions', $form->agent_instructions),
            is_active: (bool) $keep('is_active', $form->is_active),
        );
    }
}
