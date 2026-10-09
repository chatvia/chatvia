<?php

namespace Domain\Form\Data;

/**
 * What came of asking to show a form in a conversation. Exactly one of three
 * outcomes:
 *
 *  - rendered: a fresh form instance was issued ({@see $form});
 *  - already submitted: this visitor's session completed that form before
 *    ({@see $alreadySubmittedFormName}), so nothing was issued;
 *  - not found: no active form matched, so nothing was issued;
 *    {@see $activeSlugs} lists (up to 8 of) the agent's active form slugs,
 *    empty when the agent has none.
 */
final readonly class RenderFormOutcomeData
{
    /**
     * @param  list<string>  $activeSlugs
     */
    private function __construct(
        public ?InlineFormData $form = null,
        public ?string $alreadySubmittedFormName = null,
        public array $activeSlugs = [],
    ) {}

    public static function rendered(InlineFormData $form): self
    {
        return new self(form: $form);
    }

    public static function alreadySubmitted(string $formName): self
    {
        return new self(alreadySubmittedFormName: $formName);
    }

    /**
     * @param  list<string>  $activeSlugs
     */
    public static function notFound(array $activeSlugs): self
    {
        return new self(activeSlugs: $activeSlugs);
    }
}
