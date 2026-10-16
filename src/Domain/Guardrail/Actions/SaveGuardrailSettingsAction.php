<?php

declare(strict_types=1);

namespace Domain\Guardrail\Actions;

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentTextTranslator;
use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Guardrail\Data\BlockedTopicData;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Saves an agent's guardrail settings (the `guardrails` settings group) in one
 * transaction, so the guards never run on a half-saved configuration.
 *
 * First it translates what visitors read instead of an answer — the refusal
 * message and each blocked topic's reply — into the agent's languages, so a
 * blocked visitor reads it in their own language. That is one LLM call, made
 * only for a text that changed or misses a language. When it fails, the
 * settings are still saved and the texts are shown as typed.
 */
class SaveGuardrailSettingsAction
{
    public function __construct(
        private readonly AgentTextTranslator $translator,
    ) {}

    public function execute(Agent $agent, GuardrailSettingsData $settings): void
    {
        $settings = $this->withTranslations($agent, $settings, GuardrailSettingsData::fromAgent($agent));

        DB::transaction(function () use ($agent, $settings): void {
            foreach ($settings->toSettings() as $key => $value) {
                $agent->setSetting(key: $key, value: $value, group: 'guardrails');
            }
        });
    }

    /**
     * The settings with the translations of the refusal and the topic replies:
     * kept from the saved settings when a text is unchanged and covers every
     * language, else made anew. No refusal message means the generic one,
     * which the app's language files already translate.
     */
    private function withTranslations(Agent $agent, GuardrailSettingsData $settings, GuardrailSettingsData $previous): GuardrailSettingsData
    {
        /** @var array<string, TranslatableText> $texts */
        $texts = [];
        $refusal = trim((string) $settings->refusal_message);

        if ($refusal !== '') {
            $unchanged = trim((string) $previous->refusal_message) === $refusal;
            $texts['refusal'] = new TranslatableText($refusal, $unchanged ? $previous->refusal_translations : []);
        }

        foreach ($settings->blocked_topics as $index => $topic) {
            if ($topic->reply !== null) {
                $before = array_find($previous->blocked_topics, static fn (BlockedTopicData $saved): bool => $saved->reply === $topic->reply);
                $texts['topic_'.$index] = new TranslatableText($topic->reply, $before->reply_translations ?? []);
            }
        }

        $texts = $this->translateMissing($agent, $texts);

        return new GuardrailSettingsData(
            enabled: $settings->enabled,
            filters: $settings->filters,
            injection: $settings->injection,
            toxicity: $settings->toxicity,
            blocked_topics: array_map(
                static fn (BlockedTopicData $topic, int $index): BlockedTopicData => new BlockedTopicData(
                    name: $topic->name,
                    description: $topic->description,
                    examples: $topic->examples,
                    action: $topic->action,
                    reply: $topic->reply,
                    reply_translations: $texts['topic_'.$index]->translations ?? [],
                ),
                $settings->blocked_topics,
                array_keys($settings->blocked_topics),
            ),
            allowed_scope: $settings->allowed_scope,
            refusal_message: $settings->refusal_message,
            allowlist: $settings->allowlist,
            refusal_translations: $texts['refusal']->translations ?? [],
            pii_actions: $settings->pii_actions,
            custom_patterns: $settings->custom_patterns,
            injection_mode: $settings->injection_mode,
            injection_sensitivity: $settings->injection_sensitivity,
            toxicity_mode: $settings->toxicity_mode,
            toxicity_sensitivity: $settings->toxicity_sensitivity,
            topics_mode: $settings->topics_mode,
            check_before_showing: $settings->check_before_showing,
            alert_owner: $settings->alert_owner,
        );
    }

    /**
     * @param  array<string, TranslatableText>  $texts
     * @return array<string, TranslatableText>
     */
    private function translateMissing(Agent $agent, array $texts): array
    {
        $locales = array_keys($agent->enabledLanguages());
        $missing = array_filter($texts, static fn (TranslatableText $text): bool => ! $text->isTranslatedInto($locales));

        if ($missing === []) {
            return $texts;
        }

        try {
            return [...$texts, ...$this->translator->translate($agent, $missing)];
        } catch (Throwable $e) {
            report($e);

            return $texts;
        }
    }
}
