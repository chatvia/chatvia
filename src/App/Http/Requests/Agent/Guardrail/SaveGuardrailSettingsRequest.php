<?php

namespace App\Http\Requests\Agent\Guardrail;

use Closure;
use Domain\Guardrail\Data\BlockedTopicData;
use Domain\Guardrail\Data\CustomPiiPatternData;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\GuardMode;
use Domain\Guardrail\Enums\GuardSensitivity;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Enums\PiiHandling;
use Domain\Guardrail\Enums\TopicResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates the per-agent guardrail settings form. The route already enforces
 * `agent.permission:agent.guardrails.manage`, so authorize() defers to it.
 * The Guardrails page's "Test a message" sends the same fields, so it reuses
 * these rules and {@see toData()}.
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
            'filters.*' => ['string', Rule::enum(PiiFilterType::class)],
            'pii_actions' => ['nullable', 'array'],
            'pii_actions.*' => [Rule::enum(PiiHandling::class)],
            'custom_patterns' => ['nullable', 'array', 'max:20'],
            'custom_patterns.*.name' => ['required', 'string', 'max:40', 'regex:/^[\pL\pN ]+$/u', 'distinct:ignore_case'],
            'custom_patterns.*.pattern' => ['required', 'string', 'max:200', $this->usablePattern(...)],
            'custom_patterns.*.action' => ['nullable', Rule::enum(PiiHandling::class)],
            'allowlist' => ['nullable', 'array', 'max:50'],
            'allowlist.*' => ['string', 'max:254'],
            'injection' => ['required', 'boolean'],
            'injection_mode' => ['nullable', Rule::enum(GuardMode::class)],
            'injection_sensitivity' => ['nullable', Rule::enum(GuardSensitivity::class)],
            'toxicity' => ['required', 'boolean'],
            'toxicity_mode' => ['nullable', Rule::enum(GuardMode::class)],
            'toxicity_sensitivity' => ['nullable', Rule::enum(GuardSensitivity::class)],
            'topics_mode' => ['nullable', Rule::enum(GuardMode::class)],
            'blocked_topics' => ['nullable', 'array', 'max:30'],
            'blocked_topics.*.name' => ['required', 'string', 'max:120'],
            'blocked_topics.*.description' => ['nullable', 'string', 'max:300'],
            'blocked_topics.*.examples' => ['nullable', 'array', 'max:5'],
            'blocked_topics.*.examples.*' => ['nullable', 'string', 'max:200'],
            'blocked_topics.*.action' => ['nullable', Rule::enum(TopicResponse::class)],
            'blocked_topics.*.reply' => ['nullable', 'string', 'max:500'],
            'allowed_scope' => ['nullable', 'string', 'max:2000'],
            'refusal_message' => ['nullable', 'string', 'max:500'],
            'check_answers' => ['nullable', 'boolean'],
            'check_before_showing' => ['nullable', 'boolean'],
            'alert_owner' => ['nullable', 'boolean'],
        ];
    }

    public function toData(): GuardrailSettingsData
    {
        $customPatterns = array_values(array_filter(array_map(
            CustomPiiPatternData::fromStored(...),
            (array) $this->input('custom_patterns', []),
        )));

        return new GuardrailSettingsData(
            enabled: $this->boolean('enabled'),
            // Validated against the catalog and re-ordered into canonical pipeline order.
            filters: array_values(array_intersect(PiiFilterType::keys(), (array) $this->input('filters', []))),
            injection: $this->boolean('injection'),
            toxicity: $this->boolean('toxicity'),
            blocked_topics: array_values(array_filter(array_map(
                BlockedTopicData::fromStored(...),
                (array) $this->input('blocked_topics', []),
            ))),
            allowed_scope: $this->string('allowed_scope')->toString() ?: null,
            refusal_message: $this->string('refusal_message')->toString() ?: null,
            allowlist: array_values(array_unique(array_filter(
                array_map(Str::trim(...), (array) $this->input('allowlist', [])),
                filled(...),
            ))),
            // Only the built-in filters; a custom pattern carries its own action.
            pii_actions: array_intersect_key((array) $this->input('pii_actions', []), array_flip(PiiFilterType::keys())),
            custom_patterns: $customPatterns,
            injection_mode: $this->enum('injection_mode', GuardMode::class) ?? GuardMode::Block,
            injection_sensitivity: $this->enum('injection_sensitivity', GuardSensitivity::class) ?? GuardSensitivity::High,
            toxicity_mode: $this->enum('toxicity_mode', GuardMode::class) ?? GuardMode::Block,
            toxicity_sensitivity: $this->enum('toxicity_sensitivity', GuardSensitivity::class) ?? GuardSensitivity::Medium,
            topics_mode: $this->enum('topics_mode', GuardMode::class) ?? GuardMode::Block,
            // Left out (an older form or client): answers stay checked.
            check_answers: $this->boolean('check_answers', true),
            check_before_showing: $this->boolean('check_before_showing'),
            alert_owner: $this->boolean('alert_owner'),
        );
    }

    /**
     * A custom pattern must compile and must not match empty text (it would
     * then match between every two characters).
     */
    private function usablePattern(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = (string) $value;

        if (! CustomPiiPatternData::isValidPattern($pattern)) {
            $fail(__('This is not a valid pattern.'));

            return;
        }

        if (Str::isMatch(new CustomPiiPatternData('check', $pattern)->regex(), '')) {
            $fail(__('The pattern must not match empty text.'));
        }
    }
}
