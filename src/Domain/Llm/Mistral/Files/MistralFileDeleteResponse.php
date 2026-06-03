<?php

namespace Domain\Llm\Mistral\Files;

use Spatie\LaravelData\Data;

class MistralFileDeleteResponse extends Data
{
    public function __construct(
        public string $id,
        public string $object,
        public bool $deleted,
    ) {}
}
