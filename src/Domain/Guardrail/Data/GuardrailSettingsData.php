<?php

namespace Domain\Guardrail\Data;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\PiiFilterType;
use Spatie\LaravelData\Data;

/**
 * The per-agent guardrail configuration (the `guardrails` model-settings group).
 * Built from the SaveGuardrailSettingsRequest on save and from the agent's
 * stored settings ({@see self::fromAgent()}) when the guards run or the page
 * shows them; `toSettings()` is the key→value map persisted via
 * Agent::setSetting.
 *
 * Defaults for an agent that never saved the form: guards off, the default PII
 * filters, injection and toxicity checks on, no topic policy, and the generic
 * refusal message.
 */
class GuardrailSettingsData extends Data
{
    /**
     * @param  list<string>  $filters  Selected PII filter keys (see PiiFilterType).
     * @param  list<string>  $blocked_topics
     */
    public function __construct(
        public bool $enabled,
        public array $filters,
        public bool $injection,
        public bool $toxicity,
        public array $blocked_topics,
        public ?string $allowed_scope,
        public ?string $refusal_message,
    ) {}

    public static function fromAgent(Agent $agent): self
    {
        $settings = $agent->settings('guardrails');
        $filters = $settings->get('filters', PiiFilterType::defaults());

        return new self(
            enabled: (bool) $settings->get('enabled', false),
            // An empty stored array means PII redaction is off.
            filters: is_array($filters) ? array_values($filters) : [],
            injection: (bool) $settings->get('injection', true),
            toxicity: (bool) $settings->get('toxicity', true),
            blocked_topics: self::cleanList($settings->get('blocked_topics')),
            allowed_scope: (string) $settings->get('allowed_scope', ''),
            refusal_message: (string) $settings->get('refusal_message', ''),
        );
    }

    /**
     * The selected PII filters that exist, in canonical pipeline order.
     *
     * @return list<string>
     */
    public function piiFilters(): array
    {
        return array_values(array_intersect(PiiFilterType::keys(), $this->filters));
    }

    /**
     * Whether the topic check runs: there is a blocked list or an allowed scope.
     */
    public function hasTopicPolicy(): bool
    {
        return $this->blocked_topics !== [] || $this->allowedScope() !== null;
    }

    public function allowedScope(): ?string
    {
        $scope = trim((string) $this->allowed_scope);

        return $scope !== '' ? $scope : null;
    }

    /**
     * The refusal shown when a guard blocks: the agent's own message, or the
     * generic one.
     */
    public function refusalMessage(): string
    {
        $message = trim((string) $this->refusal_message);

        return $message !== '' ? $message : __('Sorry, I can’t help with that request.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSettings(): array
    {
        return [
            'enabled' => $this->enabled,
            'filters' => array_values($this->filters),
            'injection' => $this->injection,
            'toxicity' => $this->toxicity,
            'blocked_topics' => $this->blocked_topics,
            'allowed_scope' => $this->allowed_scope ?? '',
            'refusal_message' => $this->refusal_message ?? '',
        ];
    }

    /**
     * @return list<string>
     */
    private static function cleanList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => is_string($item) ? trim($item) : '', $value),
            fn (string $item): bool => $item !== '',
        ));
    }
}
