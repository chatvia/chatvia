<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Chat;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * A JSON schema the model's reply must follow (OpenAI `response_format` of
 * type `json_schema`). With `strict` the provider rejects any other shape.
 * A model the provider cannot hold to a schema is given it as an
 * instruction instead ({@see self::instruction()}).
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

    /** The schema as a prompt instruction, for a model that cannot be held to it. */
    public function instruction(): string
    {
        return Str::trim(prompt('Llm/json/schema-reply', [
            'schema' => json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]));
    }
}
