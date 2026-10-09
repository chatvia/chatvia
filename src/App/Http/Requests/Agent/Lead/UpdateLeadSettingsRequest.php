<?php

namespace App\Http\Requests\Agent\Lead;

use Domain\Form\Data\LeadFieldData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An agent's lead-capture field settings. Access is gated by the
 * `agent.permission:agent.leads.settings` route middleware.
 */
class UpdateLeadSettingsRequest extends FormRequest
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
            'fields' => ['required', 'array'],
            'fields.*.title' => ['required', 'string'],
            'fields.*.required' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<LeadFieldData>
     */
    public function toData(): array
    {
        return array_values(array_map(
            fn (array $field): LeadFieldData => new LeadFieldData(
                title: (string) $field['title'],
                required: filter_var($field['required'], FILTER_VALIDATE_BOOLEAN),
            ),
            (array) $this->validated('fields'),
        ));
    }
}
