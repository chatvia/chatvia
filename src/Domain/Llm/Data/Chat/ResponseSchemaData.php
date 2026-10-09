<?php

namespace Domain\Llm\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * A JSON schema the model's reply must follow (OpenAI `response_format` of
 * type `json_schema`). With `strict` the provider rejects any other shape.
 */
final class ResponseSchemaData extends Data
{
    /**
     * @param  array<string, mixed>  $schema  the JSON schema of the reply
     */
    public function __construct(
        public string $name,
        public array $schema,
        public bool $strict = true,
    ) {}

    /**
     * @return array{type: string, json_schema: array<string, mixed>}
     */
    public function toResponseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $this->name,
                'strict' => $this->strict,
                'schema' => $this->schema,
            ],
        ];
    }
}
