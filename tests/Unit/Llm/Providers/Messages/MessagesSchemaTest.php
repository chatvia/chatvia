<?php

declare(strict_types=1);

use Domain\Llm\Providers\Messages\MessagesPayload;
use Domain\Llm\Providers\Messages\MessagesSchema;

/**
 * A reply's JSON schema rewritten for Anthropic's structured outputs: the
 * limits it refuses move into the description and every object is closed.
 */
it('moves array sizes above one item into the description', function (): void {
    $schema = MessagesSchema::from([
        'type' => 'object',
        'properties' => [
            'questions' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 5, 'maxItems' => 7, 'description' => 'The questions.'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
        ],
        'required' => ['questions', 'tags'],
        'additionalProperties' => false,
    ]);

    expect($schema['properties']['questions'])->toBe(['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The questions. (maxItems: 7, minItems: 5)'])
        ->and($schema['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1]);
});

it('drops number and length limits at any depth', function (): void {
    $schema = MessagesSchema::from([
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'name' => ['anyOf' => [['type' => 'string', 'maxLength' => 40], ['type' => 'null']]],
                ],
            ]],
        ],
    ]);

    $item = $schema['properties']['items']['items'];

    expect($item['properties']['score'])->toBe(['type' => 'number', 'description' => '(minimum: 0, maximum: 1)'])
        ->and($item['properties']['name']['anyOf'][0])->toBe(['type' => 'string', 'description' => '(maxLength: 40)'])
        ->and($item['additionalProperties'])->toBeFalse();
});

it('closes every object', function (): void {
    $schema = MessagesSchema::from(['type' => 'object', 'properties' => ['meta' => ['type' => 'object', 'additionalProperties' => true]]]);

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['meta']['additionalProperties'])->toBeFalse();
});

it('leaves a schema it accepts as it is', function (): void {
    $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean'], 'kind' => ['type' => 'string', 'enum' => ['a', 'b']]], 'required' => ['ok', 'kind'], 'additionalProperties' => false];

    expect(MessagesSchema::from($schema))->toBe($schema);
});

it('rewrites the schema of a structured reply request', function (): void {
    $schema = ['type' => 'object', 'properties' => ['questions' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 5]], 'required' => ['questions'], 'additionalProperties' => false];

    $body = MessagesPayload::fromChatCompletions(['messages' => [], 'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'chunk', 'strict' => true, 'schema' => $schema]]]);

    expect($body['output_config']['format']['schema']['properties']['questions'])->not->toHaveKey('minItems');
});
