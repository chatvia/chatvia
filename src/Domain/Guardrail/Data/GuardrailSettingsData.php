<?php

namespace Domain\Guardrail\Data;

use Domain\Agent\Models\Agent;
use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Guardrail\Enums\GuardMode;
use Domain\Guardrail\Enums\GuardSensitivity;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Enums\PiiHandling;
use Spatie\LaravelData\Data;

/**
 * The per-agent guardrail configuration (the `guardrails` model-settings group).
 * Built from the SaveGuardrailSettingsRequest on save and from the agent's
 * stored settings ({@see self::fromAgent()}) when the guards run or the page
 * shows them; `toSettings()` is the key→value map persisted via
 * Agent::setSetting.
 *
 * Defaults for an agent that never saved the form: guards on, the default PII
 * filters (masked), injection and toxicity checks on and blocking, no topic
 * policy, nothing on the PII allowlist, the generic refusal message, answers
 * streamed as they are written, and no alert emails.
 *
 * Each classifier guard has a mode (block, or monitor = log only) and, for
 * injection and toxicity, a sensitivity: injection blocks from a low-risk
 * finding up by default, toxicity from medium up.
 *
 * `refusal_translations` (locale → text) are the AI translations of the
 * owner's refusal message, written by SaveGuardrailSettingsAction (like each
 * topic's `reply_translations`).
 */
class GuardrailSettingsData extends Data
{
    /**
     * @param  list<string>  $filters  Selected PII filter keys (see PiiFilterType).
     * @param  list<BlockedTopicData>  $blocked_topics
     * @param  list<string>  $allowlist  Values the PII filters never mask (emails, `@domain`s, numbers).
     * @param  array<string, string>  $refusal_translations
     * @param  array<string, string>  $pii_actions  Filter key → PiiHandling value; a missing key masks.
     * @param  list<CustomPiiPatternData>  $custom_patterns
     */
    public function __construct(
        public bool $enabled,
        public array $filters,
        public bool $injection,
        public bool $toxicity,
        public array $blocked_topics,
        public ?string $allowed_scope,
        public ?string $refusal_message,
        public array $allowlist = [],
        public array $refusal_translations = [],
        public array $pii_actions = [],
        public array $custom_patterns = [],
        public GuardMode $injection_mode = GuardMode::Block,
        public GuardSensitivity $injection_sensitivity = GuardSensitivity::High,
        public GuardMode $toxicity_mode = GuardMode::Block,
        public GuardSensitivity $toxicity_sensitivity = GuardSensitivity::Medium,
        public GuardMode $topics_mode = GuardMode::Block,
        public bool $check_before_showing = false,
        public bool $alert_owner = false,
    ) {}

    public static function fromAgent(Agent $agent): self
    {
        $settings = $agent->settings('guardrails');
        $filters = $settings->get('filters', PiiFilterType::defaults());

        return new self(
            enabled: (bool) $settings->get('enabled', true),
            // An empty stored array means PII redaction is off.
            filters: is_array($filters) ? array_values($filters) : [],
            injection: (bool) $settings->get('injection', true),
            toxicity: (bool) $settings->get('toxicity', true),
            blocked_topics: array_values(array_filter(array_map(
                BlockedTopicData::fromStored(...),
                (array) ($settings->get('blocked_topics') ?? []),
            ))),
            allowed_scope: (string) $settings->get('allowed_scope', ''),
            refusal_message: (string) $settings->get('refusal_message', ''),
            allowlist: self::cleanList($settings->get('allowlist')),
            refusal_translations: array_filter((array) ($settings->get('refusal_translations') ?? []), is_string(...)),
            pii_actions: array_filter((array) ($settings->get('pii_actions') ?? []), static fn (mixed $action): bool => PiiHandling::tryFrom((string) $action) !== null),
            custom_patterns: array_values(array_filter(array_map(
                CustomPiiPatternData::fromStored(...),
                (array) ($settings->get('custom_patterns') ?? []),
            ))),
            injection_mode: GuardMode::tryFrom((string) $settings->get('injection_mode')) ?? GuardMode::Block,
            injection_sensitivity: GuardSensitivity::tryFrom((string) $settings->get('injection_sensitivity')) ?? GuardSensitivity::High,
            toxicity_mode: GuardMode::tryFrom((string) $settings->get('toxicity_mode')) ?? GuardMode::Block,
            toxicity_sensitivity: GuardSensitivity::tryFrom((string) $settings->get('toxicity_sensitivity')) ?? GuardSensitivity::Medium,
            topics_mode: GuardMode::tryFrom((string) $settings->get('topics_mode')) ?? GuardMode::Block,
            check_before_showing: (bool) $settings->get('check_before_showing', false),
            alert_owner: (bool) $settings->get('alert_owner', false),
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
     * What to do with a visitor message holding this kind of personal data (a
     * built-in filter key or a custom pattern's type).
     */
    public function piiAction(string $type): PiiHandling
    {
        foreach ($this->custom_patterns as $pattern) {
            if ($pattern->type() === $type) {
                return $pattern->action;
            }
        }

        return PiiHandling::tryFrom($this->pii_actions[$type] ?? '') ?? PiiHandling::Mask;
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

    /** The blocked topic with this 1-based number (as the classifier names it), or null. */
    public function topic(int $number): ?BlockedTopicData
    {
        return $this->blocked_topics[$number - 1] ?? null;
    }

    /**
     * The refusal shown when a guard blocks, in the visitor's language when it
     * is known: the agent's own message (or its translation), else the generic
     * one from the app's language files.
     */
    public function refusalMessage(?string $language = null): string
    {
        $message = trim((string) $this->refusal_message);

        if ($message === '') {
            return __('Sorry, I can’t help with that request.', locale: TranslatableText::normalizeLocale($language));
        }

        return (string) new TranslatableText($message, $this->refusal_translations)->in($language);
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
            'blocked_topics' => array_map(static fn (BlockedTopicData $topic): array => $topic->toStored(), $this->blocked_topics),
            'allowed_scope' => $this->allowed_scope ?? '',
            'refusal_message' => $this->refusal_message ?? '',
            'refusal_translations' => $this->refusal_translations,
            'allowlist' => $this->allowlist,
            'pii_actions' => $this->pii_actions,
            'custom_patterns' => array_map(static fn (CustomPiiPatternData $pattern): array => $pattern->toStored(), $this->custom_patterns),
            'injection_mode' => $this->injection_mode->value,
            'injection_sensitivity' => $this->injection_sensitivity->value,
            'toxicity_mode' => $this->toxicity_mode->value,
            'toxicity_sensitivity' => $this->toxicity_sensitivity->value,
            'topics_mode' => $this->topics_mode->value,
            'check_before_showing' => $this->check_before_showing,
            'alert_owner' => $this->alert_owner,
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
