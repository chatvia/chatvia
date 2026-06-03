<?php

namespace Domain\Llm\Mistral\Files;

use Spatie\LaravelData\Data;

class MistralFileSignedUrlResponse extends Data
{
    public function __construct(
        public string $url,
    ) {}
}
