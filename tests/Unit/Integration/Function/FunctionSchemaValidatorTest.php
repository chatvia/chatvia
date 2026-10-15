<?php

use Domain\Integration\Function\Services\FunctionSchemaValidator;

/**
 * Shape checks on a function's parameters schema: an object whose properties
 * an LLM can fill, with valid names, known types, a `required` list of
 * declared properties and bounded nesting.
 */
beforeEach(function (): void {
    $this->validator = new FunctionSchemaValidator;
});

it('accepts a minimal well-formed object schema', function (): void {
    // Laravel decodes incoming JSON {} as an empty PHP array; the
    // validator must accept that representation.
    expect($this->validator->validateShape([
        'type' => 'object',
        'properties' => [],
        'required' => [],
    ]))->toBe([]);
});

it('accepts the get_weather template', function (): void {
    expect($this->validator->isValidShape([
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'City name'],
        ],
        'required' => ['city'],
    ]))->toBeTrue();
});

it('rejects non-object input', function (): void {
    $errors = $this->validator->validateShape('not an object');

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('must be a JSON object');
});

it('rejects a non-object root type', function (): void {
    expect($this->validator->validateShape(['type' => 'string']))
        ->toContain('The parameters schema must have type "object".');
});

it('rejects invalid property names', function (): void {
    $errors = $this->validator->validateShape([
        'type' => 'object',
        'properties' => [
            'good_one' => ['type' => 'string'],
            '1bad' => ['type' => 'string'],
            'with space' => ['type' => 'string'],
        ],
    ]);

    expect(implode("\n", $errors))->toContain('1bad')->toContain('with space');
});

it('rejects unsupported property types', function (): void {
    $errors = $this->validator->validateShape([
        'type' => 'object',
        'properties' => [
            'x' => ['type' => 'function'],
        ],
    ]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('unsupported type');
});

it('rejects a required entry that names an undeclared property', function (): void {
    $errors = $this->validator->validateShape([
        'type' => 'object',
        'properties' => ['a' => ['type' => 'string']],
        'required' => ['a', 'b'],
    ]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('"b"');
});

it('requires required to be a list', function (): void {
    expect($this->validator->validateShape([
        'type' => 'object',
        'properties' => ['a' => ['type' => 'string']],
        'required' => ['key' => 'a'],
    ]))->toContain('The parameters schema "required" must be an array of property names.');
});

it('recurses into nested objects', function (): void {
    $errors = $this->validator->validateShape([
        'type' => 'object',
        'properties' => [
            'address' => [
                'type' => 'object',
                'properties' => [
                    'bad-name' => ['type' => 'string'],
                ],
            ],
        ],
    ]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('bad-name');
});

it('recurses into array items', function (): void {
    $errors = $this->validator->validateShape([
        'type' => 'object',
        'properties' => [
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'nope'],
            ],
        ],
    ]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('tags[]');
});

it('accepts union type arrays for nullable fields', function (): void {
    expect($this->validator->validateShape([
        'type' => 'object',
        'properties' => [
            'maybe' => ['type' => ['string', 'null']],
        ],
    ]))->toBe([]);
});

it('rejects too deep nesting', function (): void {
    $deep = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];
    for ($i = 0; $i < 10; $i++) {
        $deep = ['type' => 'object', 'properties' => ['nest' => $deep]];
    }

    $errors = $this->validator->validateShape($deep);

    expect($errors)->not->toBeEmpty()
        ->and(implode("\n", $errors))->toContain('too deeply');
});
