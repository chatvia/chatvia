<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\Copy\BubbleData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentTextTranslator;
use Domain\Agent\ValueObjects\TranslatableText;

/**
 * Replace an agent's widget chat-bubble prompts and (re)generate their AI
 * translations into every enabled language. Mirrors {@see SetAgentStartersAction}:
 * the owner's manual per-language edits win over the AI output and are kept
 * across saves. Bubbles have no stable id, so a bubble whose base text matches
 * a stored one and is already translated into every enabled language keeps
 * those translations; only new or changed bubbles (or a newly enabled
 * language) reach the translator.
 */
class SetWidgetBubblesAction
{
    public function __construct(
        private readonly AgentTextTranslator $translator,
    ) {}

    /**
     * @param  list<BubbleData>  $bubbles
     * @return list<BubbleData>
     */
    public function execute(Agent $agent, array $bubbles): array
    {
        $languages = $agent->enabledLanguages();
        $locales = array_keys($languages);
        $previous = $this->previousCopy($agent);

        /** @var array<string, TranslatableText> $copies keyed by a short key the translator sees */
        $copies = [];

        foreach (array_values($bubbles) as $index => $bubble) {
            $text = trim($bubble->text);

            if ($text === '') {
                continue;
            }

            $copy = new TranslatableText($text, [], TranslatableText::onlyLanguages($bubble->overrides, $languages));
            $before = $previous[$text] ?? null;

            if ($before !== null && $before->isTranslatedInto($locales)) {
                $copy = $copy->withTranslations($before->translations);
            }

            $copies['k'.$index] = $copy;
        }

        $pending = array_filter($copies, static fn (TranslatableText $copy): bool => ! $copy->isTranslatedInto($locales));
        $copies = [...$copies, ...$this->translator->translate($agent, $pending)];

        $final = array_values(array_map(BubbleData::fromCopy(...), $copies));

        $agent->setSetting('bubbles', array_map(static fn (BubbleData $bubble): array => $bubble->toArray(), $final), 'widget');

        return $final;
    }

    /**
     * The stored bubbles' copy, keyed by base text.
     *
     * @return array<string, TranslatableText>
     */
    private function previousCopy(Agent $agent): array
    {
        $previous = [];

        foreach ($agent->normalizedBubbles() as $bubble) {
            $previous[$bubble['text']] = TranslatableText::fromStored($bubble);
        }

        return $previous;
    }
}
