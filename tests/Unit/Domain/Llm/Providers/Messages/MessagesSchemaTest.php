<?php

declare(strict_types=1);

use Domain\Llm\Providers\Messages\MessagesSchema;

/**
 * A reply's JSON schema rewritten for Anthropic's structured outputs: the
 * limits it refuses move into the description and every object is closed.
 */
it('moves the limits it refuses into the description at any depth, keeping an array size of one item', function (): void {
    $schema = MessagesSchema::from([
        'type' => 'object',
        'properties' => [
            'questions' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 5, 'maxItems' => 7, 'description' => 'The questions.'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
            'items' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'name' => ['anyOf' => [['type' => 'string', 'maxLength' => 40], ['type' => 'null']]],
                ],
            ]],
        ],
        'required' => ['questions', 'tags'],
        'additionalProperties' => false,
    ]);

    $item = $schema['properties']['items']['items'];

    expect($schema['properties']['questions'])->toBe(['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The questions. (maxItems: 7, minItems: 5)'])
        ->and($schema['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1])
        ->and($item['properties']['score'])->toBe(['type' => 'number', 'description' => '(minimum: 0, maximum: 1)'])
        ->and($item['properties']['name']['anyOf'][0])->toBe(['type' => 'string', 'description' => '(maxLength: 40)']);
});

it('closes every object and leaves a schema it accepts as it is', function (): void {
    $schema = MessagesSchema::from(['type' => 'object', 'properties' => ['meta' => ['type' => 'object', 'additionalProperties' => true]]]);
    $accepted = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean'], 'kind' => ['type' => 'string', 'enum' => ['a', 'b']]], 'required' => ['ok', 'kind'], 'additionalProperties' => false];

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['meta']['additionalProperties'])->toBeFalse()
        ->and(MessagesSchema::from($accepted))->toBe($accepted);
});
