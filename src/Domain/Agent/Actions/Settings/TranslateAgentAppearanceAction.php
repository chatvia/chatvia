<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\AppearanceTextData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentTextTranslator;
use Domain\Agent\ValueObjects\TranslatableText;

/**
 * Persists the agent's three base appearance strings and (re)generates their
 * AI translations for every enabled language. Manual per-locale edits live in
 * a separate `text_overrides` setting and are never touched here, so an owner's
 * corrections survive a re-translate. Returns the fresh translations map.
 */
class TranslateAgentAppearanceAction
{
    public function __construct(
        private readonly AgentTextTranslator $translator,
    ) {}

    /**
     * @return array<string, array<string, string>> field => (locale => translation)
     */
    public function execute(Agent $agent, AppearanceTextData $data): array
    {
        $current = $agent->settings('appearance');
        $existing = (array) ($current->get('text_translations') ?? []);

        $unchanged = (string) ($current->get('chat_window_title') ?? '') === $data->chat_window_title
            && (string) ($current->get('welcome_message') ?? '') === $data->welcome_message
            && (string) ($current->get('composer_placeholder') ?? '') === $data->composer_placeholder;

        // Persist the base (source-language) strings — idempotent updateOrCreate.
        $agent->setSetting('chat_window_title', $data->chat_window_title, 'appearance');
        $agent->setSetting('welcome_message', $data->welcome_message, 'appearance');
        $agent->setSetting('composer_placeholder', $data->composer_placeholder, 'appearance');

        /** @var array<string, TranslatableText> $copies field => copy, empty fields left out */
        $copies = [];

        foreach ($data->toFields() as $field => $text) {
            $copies[$field] = new TranslatableText($text, (array) ($existing[$field] ?? []));
        }

        if ($copies === []) {
            $agent->setSetting('text_translations', [], 'appearance');

            return [];
        }

        // Skip the LLM round-trip only when the base text is identical AND we
        // already hold a translation for every enabled language — re-translate
        // all three when the owner just enabled a language the cached set
        // doesn't cover.
        $locales = array_keys($agent->enabledLanguages());

        if ($unchanged && $existing !== [] && array_all($copies, static fn (TranslatableText $copy): bool => $copy->isTranslatedInto($locales))) {
            return $existing;
        }

        $translations = array_filter(array_map(
            static fn (TranslatableText $copy): array => $copy->translations,
            $this->translator->translate($agent, $copies),
        ));

        $agent->setSetting('text_translations', $translations, 'appearance');

        return $translations;
    }
}
