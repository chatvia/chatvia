<?php

namespace App\Http\Requests\Agent\Guardrail;

use Domain\Guardrail\Data\GuardrailSettingsData;
use Illuminate\Foundation\Http\FormRequest;

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
            'pii' => ['required', 'boolean'],
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
            pii: $this->boolean('pii'),
            injection: $this->boolean('injection'),
            toxicity: $this->boolean('toxicity'),
            blocked_topics: array_values(array_filter(
                (array) $this->input('blocked_topics', []),
                fn ($t): bool => is_string($t) && trim($t) !== '',
            )),
            allowed_scope: $this->string('allowed_scope')->toString() ?: null,
            refusal_message: $this->string('refusal_message')->toString() ?: null,
        );
    }
}
