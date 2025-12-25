<?php

namespace App\Data\Memory;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class MessageData extends Data
{
    public function __construct(
        public string $id,
        public string $role,
        public string $content,
        public string $type,
        public int $timestamp,
        public ?int $readAt = null,
        public ?string $model = null,
        public ?bool $feedback = null,
        public ?string $language = null,
        public ?string $messageForLlm = null,
        public ?bool $llmFoundAnswer = null,
    ) {}
}
