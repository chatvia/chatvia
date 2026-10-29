<?php

use Domain\Integration\Function\Services\FunctionSchemaValidator;

/**
 * Shape checks on a function's parameters schema: an object whose properties
 * an LLM can fill, with valid names, known types (a union for a nullable
 * field), a `required` list of declared properties and bounded nesting.
 * Laravel decodes incoming JSON {} as an empty PHP array, so that form of an
 * empty object is accepted.
 */
function fsvTooDeep(): array
{
    $deep = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];
    for ($i = 0; $i < 10; $i++) {
        $deep = ['type' => 'object', 'properties' => ['nest' => $deep]];
    }

    return $deep;
}

it('accepts well-formed object schemas', function (): void {
    $validator = new FunctionSchemaValidator;

    expect($validator->validateShape(['type' => 'object', 'properties' => [], 'required' => []]))->toBe([])
        ->and($validator->validateShape([
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string', 'description' => 'City name']],
            'required' => ['city'],
        ]))->toBe([])
        ->and($validator->validateShape([
            'type' => 'object',
            'properties' => ['maybe' => ['type' => ['string', 'null']]],
        ]))->toBe([]);
});

it('rejects a malformed schema with the reason', function (mixed $schema, string $reason): void {
    $errors = (new FunctionSchemaValidator)->validateShape($schema);

    expect(implode("\n", $errors))->toContain($reason);
})->with([
    'not an object' => ['not an object', 'must be a JSON object'],
    'a non-object root type' => [['type' => 'string'], 'The parameters schema must have type "object".'],
    'an invalid property name' => [['type' => 'object', 'properties' => ['good_one' => ['type' => 'string'], '1bad' => ['type' => 'string']]], '1bad'],
    'an unsupported type' => [['type' => 'object', 'properties' => ['x' => ['type' => 'function']]], 'unsupported type'],
    'a required entry for an undeclared property' => [['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a', 'b']], '"b"'],
    'a bad name in a nested object' => [['type' => 'object', 'properties' => ['address' => ['type' => 'object', 'properties' => ['bad-name' => ['type' => 'string']]]]], 'bad-name'],
    'too deep nesting' => [fsvTooDeep(), 'too deeply'],
]);
