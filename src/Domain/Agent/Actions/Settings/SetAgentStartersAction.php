<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Str;

/**
 * Replace an agent's conversation starters with the given ordered list and
 * (re)generate their AI translations into every supported language. Each entry
 * is `{ id, text, icon, translations, overrides }`: `text` is the base copy,
 * `translations` the AI output, `overrides` the owner's manual per-language
 * edits (which win over the AI output and are preserved across saves). Only
 * new/changed base texts are re-translated, so unchanged starters skip the LLM.
 */
class SetAgentStartersAction
{
    public function __construct(
        private readonly TextTranslator $translator,
        private readonly TokenUsageContext $usage,
    ) {}

    /**
     * @param  array<int, array{id?: string|null, text: string, icon?: string|null, overrides?: array<string, string>}>  $starters
     * @return array<int, array{id: string, text: string, icon: string|null, translations: array<string, string>, overrides: array<string, string>}>
     */
    public function execute(Agent $agent, array $starters): array
    {
        $existing = collect($agent->resolvedStarters())
            ->filter(fn ($s): bool => is_array($s) && isset($s['id']))
            ->keyBy('id');

        $languages = $agent->enabledLanguages();

        $items = [];
        $toTranslate = []; // shortKey => base text (short keys keep the LLM honest)
        $keyToId = [];     // shortKey => starter id

        foreach (array_values($starters) as $i => $starter) {
            $text = trim((string) ($starter['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $id = (isset($starter['id']) && is_string($starter['id']) && $starter['id'] !== '')
                ? $starter['id']
                : (string) Str::uuid();

            $overrides = $this->cleanLocaleMap($starter['overrides'] ?? [], $languages);

            // Reuse stored translations when this starter's base text is unchanged
            // AND they already cover every enabled language (re-translate when the
            // owner just enabled a language the cached set doesn't cover yet).
            $prev = $existing->get($id);
            $reuse = is_array($prev)
                && ($prev['text'] ?? null) === $text
                && $this->covers($prev['translations'] ?? null, array_keys($languages));

            if (! $reuse) {
                $key = 'k'.$i;
                $toTranslate[$key] = $text;
                $keyToId[$key] = $id;
            }

            $items[$id] = [
                'id' => $id,
                'text' => $text,
                'icon' => $starter['icon'] ?? null,
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
                $id = $keyToId[$key] ?? null;

                if ($id !== null && isset($items[$id])) {
                    $items[$id]['translations'] = $map;
                }
            }
        }

        $final = array_values($items);

        $agent->starterSet()->updateOrCreate([], ['starters' => $final]);

        return $final;
    }

    /**
     * True when $translations already holds a non-empty entry for every enabled
     * locale — i.e. there is nothing new to translate for this starter.
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
