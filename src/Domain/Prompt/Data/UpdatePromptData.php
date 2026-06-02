<?php

namespace Domain\Prompt\Data;

use Spatie\LaravelData\Data;

class UpdatePromptData extends Data
{
    public function __construct(
        public string $name,
        public string $prompt,
    ) {}
}
