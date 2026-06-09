<?php

namespace Domain\Guardrail\Services;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\ClassifierOptionsData;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Data\GuardVerdictData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Services\Detectors\PiiDetector;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The guardrail engine: given an agent's per-agent settings, runs the enabled
 * guards over a piece of text and returns the enforcement decision (redacted
 * text, whether to block, and the verdicts to log). Pure detection — it dispatches
 * no events; the chat-pipeline integration records the verdicts.
 *
 * PII is regex (free); injection / off-topic / toxicity are one classifier call.
 */
class GuardrailService
{
    public function __construct(
        private readonly PiiDetector $pii,
        private readonly GuardrailClassifier $classifier,
    ) {}

    /**
     * Are guards active for this agent? Both the global switch and the agent's
     * own toggle must be on.
     */
    public function enabledFor(Agent $agent): bool
    {
        return (bool) config('chatvia.guardrails.enabled', true)
            && (bool) $agent->settings('guardrails')->get('enabled', false);
    }

    /**
     * PII-only redaction for the stored record. Unlike inspectInput this runs no
     * classifier (no LLM call) and never blocks — it just masks PII so a raw
     * identifier never lands in the persisted message or the replayed history.
     * Returns the text unchanged when guards are off or no filters are selected.
     */
    public function redactPii(Agent $agent, string $text): string
    {
        if (! $this->enabledFor($agent)) {
            return $text;
        }

        $filters = $this->piiFilters($agent->settings('guardrails'));

        return $filters === [] ? $text : $this->pii->redact($text, $filters)['text'];
    }

    /**
     * The PII filter keys selected for this agent, validated against the known
     * catalog and returned in canonical pipeline order. Defaults to the standard
     * set when unset; an empty stored array means PII redaction is off.
     *
     * @param  Collection<string, mixed>  $settings
     * @return list<string>
     */
    private function piiFilters(Collection $settings): array
    {
        $stored = $settings->get('filters', PiiFilterType::defaults());

        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_intersect(PiiFilterType::keys(), $stored));
    }

    /**
     * Guard the visitor's message before it reaches the LLM. PII is redacted in
     * place; a genuine injection, off-topic, or toxic message blocks the turn.
     */
    public function inspectInput(Agent $agent, string $text): GuardResultData
    {
        if (! $this->enabledFor($agent)) {
            return GuardResultData::clean($text);
        }

        $settings = $agent->settings('guardrails');
        $verdicts = [];

        // PII — regex redaction, never blocks.
        $piiFilters = $this->piiFilters($settings);
        if ($piiFilters !== []) {
            $pii = $this->pii->redact($text, $piiFilters);
            if ($pii['count'] > 0) {
                $text = $pii['text'];
                $verdicts[] = new GuardVerdictData(
                    GuardType::Pii, GuardStage::Input, GuardOutcome::Redacted,
                    detail: ['types' => $pii['types'], 'count' => $pii['count']],
                );
            }
        }

        // Injection / off-topic / toxicity — one classifier call.
        $blockedTopics = $this->blockedTopics($settings->get('blocked_topics'));
        $allowedScope = trim((string) $settings->get('allowed_scope', ''));
        $checkTopic = $blockedTopics !== [] || $allowedScope !== '';

        $options = new ClassifierOptionsData(
            checkInjection: (bool) $settings->get('injection', true),
            checkTopic: $checkTopic,
            checkToxicity: (bool) $settings->get('toxicity', true),
            blockedTopics: $blockedTopics,
            allowedScope: $allowedScope !== '' ? $allowedScope : null,
        );

        $blocked = false;
        if ($options->any()) {
            $verdict = $this->classifier->classify($text, $options);

            if ($options->checkInjection && $verdict->injection) {
                $blocked = true;
                $verdicts[] = new GuardVerdictData(
                    GuardType::PromptInjection, GuardStage::Input, GuardOutcome::Blocked,
                    severity: $verdict->injectionRisk, detail: ['reason' => $verdict->reason],
                );
            }

            if ($options->checkTopic && $verdict->offTopic) {
                $blocked = true;
                $verdicts[] = new GuardVerdictData(
                    GuardType::BlockedTopic, GuardStage::Input, GuardOutcome::Blocked,
                    severity: 'medium', detail: ['reason' => $verdict->reason],
                );
            }

            if ($options->checkToxicity && $verdict->toxic) {
                $severe = in_array($verdict->toxicitySeverity, ['medium', 'high'], true);
                $blocked = $blocked || $severe;
                $verdicts[] = new GuardVerdictData(
                    GuardType::Toxicity, GuardStage::Input,
                    $severe ? GuardOutcome::Blocked : GuardOutcome::Flagged,
                    severity: $verdict->toxicitySeverity,
                    detail: ['categories' => $verdict->toxicityCategories, 'reason' => $verdict->reason],
                );
            }
        }

        return new GuardResultData(
            text: $text,
            blocked: $blocked,
            refusalMessage: $blocked ? $this->refusalMessage($settings->get('refusal_message')) : null,
            verdicts: $verdicts,
        );
    }

    /**
     * Guard the agent's answer before it is persisted/finalized. PII is redacted;
     * a toxic answer is replaced with the refusal. Returns the corrected text.
     */
    public function inspectOutput(Agent $agent, string $text): GuardResultData
    {
        if (! $this->enabledFor($agent) || trim($text) === '') {
            return GuardResultData::clean($text);
        }

        $settings = $agent->settings('guardrails');
        $verdicts = [];
        $blocked = false;

        $piiFilters = $this->piiFilters($settings);
        if ($piiFilters !== []) {
            $pii = $this->pii->redact($text, $piiFilters);
            if ($pii['count'] > 0) {
                $text = $pii['text'];
                $verdicts[] = new GuardVerdictData(
                    GuardType::Pii, GuardStage::Output, GuardOutcome::Redacted,
                    detail: ['types' => $pii['types'], 'count' => $pii['count']],
                );
            }
        }

        if ((bool) $settings->get('toxicity', true)) {
            $verdict = $this->classifier->classify($text, new ClassifierOptionsData(checkToxicity: true));
            if ($verdict->toxic && in_array($verdict->toxicitySeverity, ['medium', 'high'], true)) {
                $blocked = true;
                $text = $this->refusalMessage($settings->get('refusal_message'));
                $verdicts[] = new GuardVerdictData(
                    GuardType::Toxicity, GuardStage::Output, GuardOutcome::Blocked,
                    severity: $verdict->toxicitySeverity,
                    detail: ['categories' => $verdict->toxicityCategories, 'reason' => $verdict->reason],
                );
            }
        }

        return new GuardResultData(text: $text, blocked: $blocked, refusalMessage: null, verdicts: $verdicts);
    }

    /**
     * Scan one retrieved RAG source chunk for injection markers (cheap rules pass).
     * A suspicious chunk is dropped — `triggered()` true + empty text — so its
     * embedded instructions never reach the model.
     */
    public function scanSource(Agent $agent, string $chunk): GuardResultData
    {
        if (! $this->enabledFor($agent) || ! (bool) $agent->settings('guardrails')->get('injection', true)) {
            return GuardResultData::clean($chunk);
        }

        $haystack = Str::lower($chunk);
        foreach ((array) config('chatvia.guardrails.injection_markers', []) as $marker) {
            if ($marker !== '' && str_contains($haystack, Str::lower((string) $marker))) {
                return new GuardResultData(
                    text: '',
                    blocked: true,
                    verdicts: [new GuardVerdictData(
                        GuardType::PromptInjection, GuardStage::Source, GuardOutcome::Blocked,
                        severity: 'high', detail: ['marker' => (string) $marker],
                    )],
                );
            }
        }

        return GuardResultData::clean($chunk);
    }

    /**
     * @return list<string>
     */
    private function blockedTopics(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v): string => is_string($v) ? trim($v) : '',
            $value,
        ), fn (string $v): bool => $v !== ''));
    }

    private function refusalMessage(mixed $configured): string
    {
        $message = is_string($configured) ? trim($configured) : '';

        return $message !== '' ? $message : 'Sorry, I can’t help with that request.';
    }
}
