<?php

namespace Domain\Form\Data;

use Domain\Form\Actions\RewriteFormDescriptionAction;
use Spatie\LaravelData\Data;

/**
 * Input for {@see RewriteFormDescriptionAction}: the draft "when to show this
 * form" instruction plus the form's name/type for context. Built by the
 * RewriteFormDescriptionRequest FormRequest's toData(); every field is optional
 * so the owner can rewrite from an empty draft.
 */
class RewriteFormDescriptionData extends Data
{
    public function __construct(
        public ?string $description,
        public ?string $name,
        public ?string $form_type,
    ) {}
}
