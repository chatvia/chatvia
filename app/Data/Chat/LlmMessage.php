<?php

namespace App\Data\Chat;

use Spatie\LaravelData\Data;

class LlmMessage extends Data
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}
}
