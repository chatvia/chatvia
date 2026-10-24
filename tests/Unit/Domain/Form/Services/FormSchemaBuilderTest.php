<?php

use Domain\Form\Services\FormSchemaBuilder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * validationRules() is the server-side check of a submitted inline form
 * (SubmitInlineFormRequest uses it), so these run its rules through the
 * validator: a value the form allows passes, anything else fails.
 */

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @param  array<string, mixed>  $values
 */
function fsbPasses(array $fields, array $values): bool
{
    return Validator::make($values, app(FormSchemaBuilder::class)->validationRules($fields))->passes();
}

const FSB_OPTIONS = [['value' => 'small', 'label' => 'Small'], ['value' => 'large', 'label' => 'Large']];

it('requires a required field and lets an optional one be left out', function (): void {
    $fields = [
        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
    ];

    expect(fsbPasses($fields, ['name' => 'Jane']))->toBeTrue()
        ->and(fsbPasses($fields, ['name' => 'Jane', 'company' => null]))->toBeTrue()
        ->and(fsbPasses($fields, ['company' => 'Acme']))->toBeFalse()
        ->and(fsbPasses($fields, ['name' => '']))->toBeFalse();
});

it('accepts a value of the field type and refuses another', function (array $field, mixed $valid, mixed $invalid): void {
    $fields = [['key' => 'answer', 'label' => 'Answer', 'required' => true, ...$field]];

    expect(fsbPasses($fields, ['answer' => $valid]))->toBeTrue()
        ->and(fsbPasses($fields, ['answer' => $invalid]))->toBeFalse();
})->with([
    'text up to 1000 characters' => [['type' => 'text'], Str::repeat('a', 1000), Str::repeat('a', 1001)],
    'a field with no type is text' => [[], 'Jane', ['not', 'a', 'string']],
    'email' => [['type' => 'email'], 'jane@acme.com', 'not-an-email'],
    'number' => [['type' => 'number'], '42.5', 'forty-two'],
    'tel up to 50 characters' => [['type' => 'tel'], '+1 555 0100', Str::repeat('1', 51)],
    'date' => [['type' => 'date'], '2026-06-15', 'not a date'],
    'textarea up to 5000 characters' => [['type' => 'textarea'], Str::repeat('a', 5000), Str::repeat('a', 5001)],
    'select from its options' => [['type' => 'select', 'options' => FSB_OPTIONS], 'large', 'medium'],
    'radio from its options' => [['type' => 'radio', 'options' => FSB_OPTIONS], 'small', 'medium'],
    'checkbox' => [['type' => 'checkbox'], true, 'maybe'],
]);

it('checks every picked option of a multi-checkbox', function (): void {
    $fields = [['key' => 'sizes', 'label' => 'Sizes', 'type' => 'multicheckbox', 'required' => true, 'options' => FSB_OPTIONS]];

    expect(fsbPasses($fields, ['sizes' => ['small', 'large']]))->toBeTrue()
        ->and(fsbPasses($fields, ['sizes' => ['small', 'medium']]))->toBeFalse()
        ->and(fsbPasses($fields, ['sizes' => 'small']))->toBeFalse()
        ->and(fsbPasses($fields, ['sizes' => []]))->toBeFalse();
});

it('lets an optional multi-checkbox be left out', function (): void {
    $fields = [['key' => 'sizes', 'label' => 'Sizes', 'type' => 'multicheckbox', 'required' => false, 'options' => FSB_OPTIONS]];

    expect(fsbPasses($fields, []))->toBeTrue();
});

it('builds no rule for a field without a key or of an unknown type', function (): void {
    $rules = app(FormSchemaBuilder::class)->validationRules([
        ['key' => '', 'label' => 'Blank key', 'type' => 'text'],
        ['label' => 'No key', 'type' => 'text'],
        ['key' => 'upload', 'label' => 'Upload', 'type' => 'file'],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
    ]);

    expect(array_keys($rules))->toBe(['email']);
});
