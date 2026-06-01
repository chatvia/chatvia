<?php

namespace Domain\Agents\Data\Prompts;

use Spatie\LaravelData\Data;

class UpdatePromptData extends Data
{
    public function __construct(
        public string $name,
        public string $prompt,
    ) {}
}
