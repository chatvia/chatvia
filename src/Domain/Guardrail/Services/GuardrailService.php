<?php

namespace Domain\Guardrail\Services;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\ClassifierOptionsData;
use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Data\GuardVerdictData;
use Domain\Guardrail\Data\PiiRedactionData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Services\Detectors\PiiDetector;
use Illuminate\Support\Str;

/**
 * The guardrail engine: given an agent's per-agent settings, runs the enabled
 * guards over a piece of text and returns the enforcement decision (redacted
 * text, whether to block, and the verdicts to log). Pure detection — it
 * dispatches no events; the chat-pipeline integration records the verdicts.
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

        return $this->redact(GuardrailSettingsData::fromAgent($agent), $text)->text;
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

        $settings = GuardrailSettingsData::fromAgent($agent);
        $verdicts = [];

        $pii = $this->redact($settings, $text);
        if ($pii->found()) {
            $verdicts[] = $this->piiVerdict($pii, GuardStage::Input);
        }

        $classifierVerdicts = $this->classifyInput($settings, $pii->text);
        $verdicts = [...$verdicts, ...$classifierVerdicts];
        $blocked = $this->anyBlocked($classifierVerdicts);

        return new GuardResultData(
            text: $pii->text,
            blocked: $blocked,
            refusalMessage: $blocked ? $settings->refusalMessage() : null,
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

        $settings = GuardrailSettingsData::fromAgent($agent);
        $verdicts = [];

        $pii = $this->redact($settings, $text);
        if ($pii->found()) {
            $verdicts[] = $this->piiVerdict($pii, GuardStage::Output);
        }

        $toxicity = $settings->toxicity ? $this->blockingToxicity($pii->text) : null;

        if (! $toxicity instanceof ClassifierVerdictData) {
            return new GuardResultData(text: $pii->text, verdicts: $verdicts);
        }

        $verdicts[] = $this->toxicityVerdict($toxicity, GuardStage::Output, GuardOutcome::Blocked);

        return new GuardResultData(text: $settings->refusalMessage(), blocked: true, verdicts: $verdicts);
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
                        severity: GuardSeverity::High, detail: ['marker' => (string) $marker],
                    )],
                );
            }
        }

        return GuardResultData::clean($chunk);
    }

    private function redact(GuardrailSettingsData $settings, string $text): PiiRedactionData
    {
        $filters = $settings->piiFilters();

        return $filters === [] ? new PiiRedactionData($text) : $this->pii->redact($text, $filters);
    }

    private function piiVerdict(PiiRedactionData $pii, GuardStage $stage): GuardVerdictData
    {
        return new GuardVerdictData(
            GuardType::Pii, $stage, GuardOutcome::Redacted,
            detail: ['types' => $pii->types, 'count' => $pii->count],
        );
    }

    /**
     * The injection / off-topic / toxicity verdicts for a visitor message, from
     * one classifier call covering only the checks the agent has on.
     *
     * @return list<GuardVerdictData>
     */
    private function classifyInput(GuardrailSettingsData $settings, string $text): array
    {
        $options = new ClassifierOptionsData(
            checkInjection: $settings->injection,
            checkTopic: $settings->hasTopicPolicy(),
            checkToxicity: $settings->toxicity,
            blockedTopics: $settings->blocked_topics,
            allowedScope: $settings->allowedScope(),
        );

        if (! $options->any()) {
            return [];
        }

        $verdict = $this->classifier->classify($text, $options);
        $verdicts = [];

        if ($options->checkInjection && $verdict->injection) {
            $verdicts[] = new GuardVerdictData(
                GuardType::PromptInjection, GuardStage::Input, GuardOutcome::Blocked,
                severity: $verdict->injectionRisk, detail: ['reason' => $verdict->reason],
            );
        }

        if ($options->checkTopic && $verdict->offTopic) {
            $verdicts[] = new GuardVerdictData(
                GuardType::BlockedTopic, GuardStage::Input, GuardOutcome::Blocked,
                severity: GuardSeverity::Medium, detail: ['reason' => $verdict->reason],
            );
        }

        if ($options->checkToxicity && $verdict->toxic) {
            $outcome = $verdict->toxicitySeverity->blocks() ? GuardOutcome::Blocked : GuardOutcome::Flagged;
            $verdicts[] = $this->toxicityVerdict($verdict, GuardStage::Input, $outcome);
        }

        return $verdicts;
    }

    /**
     * The classifier verdict when the text is toxic enough to block, else null.
     */
    private function blockingToxicity(string $text): ?ClassifierVerdictData
    {
        $verdict = $this->classifier->classify($text, new ClassifierOptionsData(checkToxicity: true));

        return $verdict->toxic && $verdict->toxicitySeverity->blocks() ? $verdict : null;
    }

    private function toxicityVerdict(ClassifierVerdictData $verdict, GuardStage $stage, GuardOutcome $outcome): GuardVerdictData
    {
        return new GuardVerdictData(
            GuardType::Toxicity, $stage, $outcome,
            severity: $verdict->toxicitySeverity,
            detail: ['categories' => $verdict->toxicityCategories, 'reason' => $verdict->reason],
        );
    }

    /**
     * @param  list<GuardVerdictData>  $verdicts
     */
    private function anyBlocked(array $verdicts): bool
    {
        return array_any($verdicts, fn (GuardVerdictData $verdict): bool => $verdict->outcome === GuardOutcome::Blocked);
    }
}
