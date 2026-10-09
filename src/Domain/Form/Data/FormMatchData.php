<?php

declare(strict_types=1);

namespace Domain\Form\Data;

/**
 * One of the agent's forms that fits what a visitor wants to do, as offered
 * to the model by form discovery. `purpose` is the admin's note on when to
 * show the form.
 */
final readonly class FormMatchData
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $purpose,
        /** The visitor's session already submitted this form. */
        public bool $alreadySubmitted,
    ) {}
}
