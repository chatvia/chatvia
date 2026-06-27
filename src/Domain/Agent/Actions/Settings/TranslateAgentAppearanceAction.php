<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\AppearanceTextData;
use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Persists the agent's three base appearance strings and (re)generates their
 * AI translations for every supported language. Manual per-locale edits live in
 * a separate `text_overrides` setting and are never touched here, so an owner's
 * corrections survive a re-translate. Returns the fresh translations map.
 */
class TranslateAgentAppearanceAction
{
    public function __construct(private readonly TextTranslator $translator) {}

    /**
     * @return array<string, array<string, string>> field => (locale => translation)
     */
    public function execute(Agent $agent, AppearanceTextData $data): array
    {
        $current = $agent->settings('appearance');

        $unchanged = (string) ($current->get('chat_window_title') ?? '') === $data->chat_window_title
            && (string) ($current->get('welcome_message') ?? '') === $data->welcome_message
            && (string) ($current->get('composer_placeholder') ?? '') === $data->composer_placeholder;

        $existing = (array) ($current->get('text_translations') ?? []);

        // Persist the base (source-language) strings — idempotent updateOrCreate.
        $agent->setSetting('chat_window_title', $data->chat_window_title, 'appearance');
        $agent->setSetting('welcome_message', $data->welcome_message, 'appearance');
        $agent->setSetting('composer_placeholder', $data->composer_placeholder, 'appearance');

        $fields = $data->toFields();

        if ($fields === []) {
            $agent->setSetting('text_translations', [], 'appearance');

            return [];
        }

        $target = $agent->enabledLanguages();

        // Skip the LLM round-trip only when the base text is identical AND we
        // already hold a translation for every enabled language — re-translate
        // when the owner just enabled a language the cached set doesn't cover.
        if ($unchanged && $existing !== [] && $this->covers($existing, $fields, array_keys($target))) {
            return $existing;
        }

        $translations = $this->translator->translate($fields, $target);

        $agent->setSetting('text_translations', $translations, 'appearance');

        return $translations;
    }

    /**
     * True when $existing already holds a non-empty translation of every field
     * into every enabled locale — i.e. there is nothing new to translate.
     *
     * @param  array<string, array<string, string>>  $existing
     * @param  array<string, string>  $fields
     * @param  array<int, string>  $locales
     */
    private function covers(array $existing, array $fields, array $locales): bool
    {
        foreach (array_keys($fields) as $field) {
            foreach ($locales as $locale) {
                $value = $existing[$field][$locale] ?? null;

                if (! is_string($value) || $value === '') {
                    return false;
                }
            }
        }

        return true;
    }
}
