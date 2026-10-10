<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Account;

use Spatie\LaravelData\Data;

/**
 * Inputs for renaming an agent. Knowledge-base agents must also re-slug their
 * `slug` (it's part of the chat URL), so it travels alongside the new
 * name when present.
 */
class RenameAgentData extends Data
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
    ) {}
}
