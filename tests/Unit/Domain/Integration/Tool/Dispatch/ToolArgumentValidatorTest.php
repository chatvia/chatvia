<?php

use Domain\Integration\Tool\Dispatch\ToolArgumentValidator;
use Domain\Integration\Tool\Exceptions\InvalidToolArgumentsException;

/**
 * The model's arguments are held to the tool's top-level schema before they
 * leave the app: undeclared keys are dropped, and a missing required key, a
 * wrong basic type or a value outside an enum stops the call with a reason
 * the model can act on.
 */
beforeEach(function (): void {
    $this->schema = [
        'type' => 'object',
        'properties' => [
            'order_id' => ['type' => 'string'],
            'count' => ['type' => 'integer'],
            'price' => ['type' => 'number'],
            'urgent' => ['type' => 'boolean'],
            'tags' => ['type' => 'array'],
            'filter' => ['type' => 'object'],
            'note' => ['type' => ['string', 'null']],
            'status' => ['type' => 'string', 'enum' => ['open', 'closed']],
        ],
        'required' => ['order_id'],
    ];
});

it('keeps declared arguments of the right types and drops the rest', function (): void {
    $arguments = [
        'order_id' => 'A-1', 'count' => 2, 'price' => 9.5, 'urgent' => true, 'tags' => ['a'],
        'filter' => ['x' => 1], 'note' => null, 'status' => 'open', 'is_admin' => true,
    ];

    expect(new ToolArgumentValidator()->validated($this->schema, $arguments))->toBe([
        'order_id' => 'A-1', 'count' => 2, 'price' => 9.5, 'urgent' => true, 'tags' => ['a'],
        'filter' => ['x' => 1], 'note' => null, 'status' => 'open',
    ]);
});

it('takes a whole float as an integer', function (): void {
    expect(new ToolArgumentValidator()->validated($this->schema, ['order_id' => 'A', 'count' => 3.0]))->toBe(['order_id' => 'A', 'count' => 3.0]);
});

it('refuses arguments that do not fit the schema', function (array $arguments, string $reason): void {
    expect(fn (): array => new ToolArgumentValidator()->validated($this->schema, $arguments))
        ->toThrow(InvalidToolArgumentsException::class, $reason);
})->with([
    'missing required' => [['count' => 1], '"order_id" is required'],
    'string as integer' => [['order_id' => 'A', 'count' => '2'], '"count" must be of type integer'],
    'number as string' => [['order_id' => 1], '"order_id" must be of type string'],
    'object as array' => [['order_id' => 'A', 'tags' => ['a' => 1]], '"tags" must be of type array'],
    'outside the enum' => [['order_id' => 'A', 'status' => 'deleted'], '"status" must be one of the allowed values'],
]);

it('keeps extra keys when the schema allows them, and checks nothing without properties', function (): void {
    $open = [...$this->schema, 'additionalProperties' => true];

    expect(new ToolArgumentValidator()->validated($open, ['order_id' => 'A', 'extra' => 1]))->toBe(['order_id' => 'A', 'extra' => 1])
        ->and(new ToolArgumentValidator()->validated(['type' => 'object'], ['anything' => 1]))->toBe(['anything' => 1]);
});
