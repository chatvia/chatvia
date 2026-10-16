<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Form;

use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Enums\LeadField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /api/v1/agents/{agent}/forms` — define a new inline form: its texts,
 * type and the field schema the chat renders (at most 30 fields). The same
 * rules as the dashboard's form builder.
 */
class StoreApiFormRequest extends FormRequest
{
    /** The field types a form field can have. */
    public const array FIELD_TYPES = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'multicheckbox', 'date'];

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
            'name' => ['required', 'string', 'max:120'],
            // Visitor-facing blurb shown under the title.
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            // What the form captures: `lead`, `booking`, `qualification` or `generic`.
            'form_type' => ['required', Rule::enum(FormType::class)],
            // Shown to the visitor after a successful submission.
            'success_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Guidance for the agent on when to offer this form.
            'agent_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Whether the agent may render the form. Defaults to true.
            'is_active' => ['sometimes', 'boolean'],

            // The field definitions the chat renders, in order (1–30 fields).
            'schema' => ['required', 'array', 'min:1', 'max:30'],
            'schema.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/i'],
            'schema.*.label' => ['required', 'string', 'max:120'],
            'schema.*.type' => ['required', Rule::in(self::FIELD_TYPES)],
            'schema.*.required' => ['boolean'],
            'schema.*.placeholder' => ['nullable', 'string', 'max:120'],
            'schema.*.options' => ['nullable', 'array'],
            'schema.*.options.*.value' => ['required_with:schema.*.options', 'string', 'max:120'],
            'schema.*.options.*.label' => ['required_with:schema.*.options', 'string', 'max:120'],

            // Maps field keys to the standard lead fields.
            'lead_field_map' => ['sometimes', 'nullable', 'array'],
            'lead_field_map.*' => ['nullable', 'string', Rule::enum(LeadField::class)],
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
            form_type: $this->enum('form_type', FormType::class),
            schema: $validated['schema'],
            lead_field_map: $validated['lead_field_map'] ?? null,
            success_message: $validated['success_message'] ?? null,
            agent_instructions: $validated['agent_instructions'] ?? null,
            is_active: $validated['is_active'] ?? true,
        );
    }
}
