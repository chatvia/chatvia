<?php

namespace Domain\Llm\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * A tool call the model asked for in a non-streaming reply. The arguments stay
 * the raw JSON string the model wrote; the caller decodes and validates them.
 */
final class ChatToolCallData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $arguments,
    ) {}
}
