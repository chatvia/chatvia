<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\Copy\StarterData;
use Domain\Agent\Data\Settings\Copy\UpdateStartersData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentTextTranslator;
use Domain\Agent\ValueObjects\TranslatableText;
use Illuminate\Support\Str;

/**
 * Replace an agent's conversation starters with the given ordered list and
 * (re)generate their AI translations into every enabled language. The owner's
 * manual per-language edits win over the AI output and are kept across saves.
 * A starter whose base text is unchanged and already translated into every
 * enabled language keeps its translations, so only new or changed starters (or
 * a newly enabled language) reach the translator.
 */
class SetAgentStartersAction
{
    public function __construct(
        private readonly AgentTextTranslator $translator,
    ) {}

    /**
     * @return list<StarterData>
     */
    public function execute(Agent $agent, UpdateStartersData $data): array
    {
        $starters = $data->starters;
        $languages = $agent->enabledLanguages();
        $previous = $this->previousCopy($agent);

        /** @var array<string, array{icon: string|null, copy: TranslatableText}> $items keyed by starter id */
        $items = [];

        foreach (array_values($starters) as $starter) {
            $text = trim($starter->text);

            if ($text === '') {
                continue;
            }

            $id = filled($starter->id) ? $starter->id : (string) Str::uuid();
            $copy = new TranslatableText($text, [], TranslatableText::onlyLanguages($starter->overrides, $languages));

            // Keep the stored translations while the base text is unchanged and
            // they cover every enabled language.
            $before = $previous[$id] ?? null;

            if ($before !== null && $before->base === $text && $before->isTranslatedInto(array_keys($languages))) {
                $copy = $copy->withTranslations($before->translations);
            }

            $items[$id] = ['icon' => $starter->icon, 'copy' => $copy];
        }

        $items = $this->translateMissing($agent, $items, array_keys($languages));

        $final = array_map(
            static fn (string $id, array $item): StarterData => StarterData::fromCopy($id, $item['icon'], $item['copy']),
            array_keys($items),
            $items,
        );

        $agent->starterSet()->updateOrCreate([], [
            'starters' => array_map(static fn (StarterData $starter): array => $starter->toArray(), $final),
        ]);

        return $final;
    }

    /**
     * The stored starters' copy, keyed by starter id.
     *
     * @return array<string, TranslatableText>
     */
    private function previousCopy(Agent $agent): array
    {
        $previous = [];

        foreach ($agent->resolvedStarters() as $starter) {
            if (is_array($starter) && isset($starter['id'])) {
                $previous[(string) $starter['id']] = TranslatableText::fromStored($starter);
            }
        }

        return $previous;
    }

    /**
     * Translate the starters that still lack a language, in one call. Short
     * keys (`k0`, `k1`, …) stand in for the ids so the model can't mangle them.
     *
     * @param  array<string, array{icon: string|null, copy: TranslatableText}>  $items
     * @param  list<string>  $locales
     * @return array<string, array{icon: string|null, copy: TranslatableText}>
     */
    private function translateMissing(Agent $agent, array $items, array $locales): array
    {
        $pending = [];

        foreach (array_keys($items) as $index => $id) {
            if (! $items[$id]['copy']->isTranslatedInto($locales)) {
                $pending['k'.$index] = $id;
            }
        }

        $translated = $this->translator->translate(
            $agent,
            array_map(static fn (string $id): TranslatableText => $items[$id]['copy'], $pending),
        );

        foreach ($translated as $key => $copy) {
            $items[$pending[$key]]['copy'] = $copy;
        }

        return $items;
    }
}
