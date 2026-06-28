<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Domain\Llm\Usage\TokenUsageContext;

/**
 * Replace an agent's widget chat-bubble prompts and (re)generate their AI
 * translations into every enabled language. Mirrors {@see SetAgentStartersAction}:
 * each entry is `{ text, translations, overrides }` — `text` is the base copy,
 * `translations` the AI output, `overrides` the owner's manual per-language edits
 * (which win over the AI output and are preserved across saves). A bubble whose
 * base text is unchanged and is already fully translated skips the LLM, so only
 * new/changed bubbles (or a newly enabled language) hit the translator.
 */
class SetWidgetBubblesAction
{
    public function __construct(
        private readonly TextTranslator $translator,
        private readonly TokenUsageContext $usage,
    ) {}

    /**
     * @param  array<int, array{text: string, overrides?: array<string, string>}>  $bubbles
     * @return array<int, array{text: string, translations: array<string, string>, overrides: array<string, string>}>
     */
    public function execute(Agent $agent, array $bubbles): array
    {
        // Reuse cached translations by base text (bubbles have no stable id).
        $existing = collect($agent->normalizedBubbles())->keyBy('text');
        $languages = $agent->enabledLanguages();

        $items = [];
        $toTranslate = []; // shortKey => base text (short keys keep the LLM honest)
        $keyToIndex = [];  // shortKey => item index

        foreach (array_values($bubbles) as $i => $bubble) {
            $text = trim((string) ($bubble['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $overrides = $this->cleanLocaleMap($bubble['overrides'] ?? [], $languages);

            $prev = $existing->get($text);
            $reuse = is_array($prev) && $this->covers($prev['translations'] ?? null, array_keys($languages));

            if (! $reuse) {
                $key = 'k'.$i;
                $toTranslate[$key] = $text;
                $keyToIndex[$key] = $i;
            }

            $items[$i] = [
                'text' => $text,
                'translations' => $reuse ? $prev['translations'] : [],
                'overrides' => $overrides,
            ];
        }

        if ($toTranslate !== [] && $languages !== []) {
            $translated = $this->usage->track(
                $agent->user_id,
                fn (): array => $this->translator->translate($toTranslate, $languages),
                'translate',
            );

            foreach ($translated as $key => $map) {
                $i = $keyToIndex[$key] ?? null;

                if ($i !== null && isset($items[$i])) {
                    $items[$i]['translations'] = $map;
                }
            }
        }

        $final = array_values($items);

        $agent->setSetting('bubbles', $final, 'widget');

        return $final;
    }

    /**
     * True when $translations already holds a non-empty entry for every enabled
     * locale — i.e. there is nothing new to translate for this bubble.
     *
     * @param  array<int, string>  $locales
     */
    private function covers(mixed $translations, array $locales): bool
    {
        if (! is_array($translations) || $translations === []) {
            return false;
        }

        foreach ($locales as $locale) {
            $value = $translations[$locale] ?? null;

            if (! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Keep only non-empty string values for known locales.
     *
     * @param  array<string, mixed>  $map
     * @param  array<string, string>  $languages
     * @return array<string, string>
     */
    private function cleanLocaleMap(array $map, array $languages): array
    {
        $clean = [];

        foreach ($map as $locale => $value) {
            $value = is_string($value) ? trim($value) : '';

            if ($value !== '' && isset($languages[(string) $locale])) {
                $clean[(string) $locale] = $value;
            }
        }

        return $clean;
    }
}
