<?php

namespace App\Http\Requests\Agent\Guardrail;

use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\PiiFilterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the per-agent guardrail settings form. The route already enforces
 * `agent.permission:agent.guardrails.manage`, so authorize() defers to it.
 */
class SaveGuardrailSettingsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['string', Rule::in(PiiFilterType::keys())],
            'injection' => ['required', 'boolean'],
            'toxicity' => ['required', 'boolean'],
            'blocked_topics' => ['nullable', 'array'],
            'blocked_topics.*' => ['string', 'max:120'],
            'allowed_scope' => ['nullable', 'string', 'max:2000'],
            'refusal_message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): GuardrailSettingsData
    {
        return new GuardrailSettingsData(
            enabled: $this->boolean('enabled'),
            // Validated against the catalog and re-ordered into canonical pipeline order.
            filters: array_values(array_intersect(PiiFilterType::keys(), (array) $this->input('filters', []))),
            injection: $this->boolean('injection'),
            toxicity: $this->boolean('toxicity'),
            blocked_topics: array_values(array_filter(
                (array) $this->input('blocked_topics', []),
                filled(...),
            )),
            allowed_scope: $this->string('allowed_scope')->toString() ?: null,
            refusal_message: $this->string('refusal_message')->toString() ?: null,
        );
    }
}
