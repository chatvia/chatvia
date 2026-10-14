<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Catalog;

use Spatie\LaravelData\Data;

/**
 * A server of your own with an OpenAI-compatible API (vLLM, Ollama, LM
 * Studio, another service): its name, address, key and extra headers. A
 * null key keeps the saved one.
 */
final class CustomLlmServerData extends Data
{
    /**
     * @param  array<string, string>  $headers  extra request headers, by name
     */
    public function __construct(
        public string $name,
        public string $baseUrl,
        public ?string $apiKey = null,
        public array $headers = [],
    ) {}
}
