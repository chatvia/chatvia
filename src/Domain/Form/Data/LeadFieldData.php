<?php

namespace Domain\Form\Data;

use Spatie\LaravelData\Data;

/**
 * One field of an agent's lead-capture settings: its title and whether the
 * visitor must fill it in.
 */
class LeadFieldData extends Data
{
    public function __construct(
        public string $title,
        public bool $required,
    ) {}
}
