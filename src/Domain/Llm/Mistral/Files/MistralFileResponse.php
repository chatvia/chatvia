<?php

namespace Domain\Llm\Mistral\Files;

use Spatie\LaravelData\Data;

class MistralFileResponse extends Data
{
    public function __construct(
        public string $id,
        public string $object,
        public ?int $bytes,
        public int $created_at,
        public string $filename,
        public string $purpose,
        public ?string $sample_type,
        public string $source,
        public ?int $num_lines,
        public ?string $mimetype,
        public ?string $signature,
        public ?bool $deleted = null,
    ) {}
}
