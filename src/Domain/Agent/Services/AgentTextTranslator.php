<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;
use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Llm\Operations\Translate\TextTranslator;
use Domain\Llm\Usage\TokenUsageContext;

/**
 * Translates an agent's owner-written copy (starters, chat bubbles, appearance
 * strings) into the agent's enabled languages with one LLM call, billed to the
 * agent's workspace. Manual per-language edits are kept as they are.
 */
final readonly class AgentTextTranslator
{
    public function __construct(
        private TextTranslator $translator,
        private TokenUsageContext $usage,
    ) {}

    /**
     * Give every text a fresh set of AI translations. The keys are sent to the
     * model as they are, so callers use short neutral keys (or field names).
     * A text the model returned nothing for ends up with no translations.
     *
     * @template TKey of string
     *
     * @param  array<TKey, TranslatableText>  $texts
     * @return array<TKey, TranslatableText>
     */
    public function translate(Agent $agent, array $texts): array
    {
        $languages = $agent->enabledLanguages();

        if ($texts === [] || $languages === []) {
            return $texts;
        }

        $translated = $this->usage->track(
            $agent->workspace_id,
            fn (): array => $this->translator->translate(
                array_map(static fn (TranslatableText $text): string => (string) $text->base, $texts),
                $languages,
            ),
        );

        foreach ($texts as $key => $text) {
            $texts[$key] = $text->withTranslations($translated[$key] ?? []);
        }

        return $texts;
    }
}
