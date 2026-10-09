<?php

declare(strict_types=1);

namespace Domain\Agent\Prompt\Data;

use Spatie\LaravelData\Data;

/**
 * Input for editing a library prompt's name and text.
 */
class UpdatePromptData extends Data
{
    public function __construct(
        public string $name,
        public string $prompt,
    ) {}
}
