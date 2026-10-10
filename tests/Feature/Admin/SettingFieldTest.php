<?php

declare(strict_types=1);

use App\Admin\Settings\SettingField;

/**
 * Setting values are saved as strings. A typed field turns a saved string into
 * the PHP type its config readers expect, and panel input back into a string.
 * A value that is blank or not valid casts to null, so the default applies.
 */
it('casts a toggle', function (mixed $saved, ?bool $expected): void {
    $field = SettingField::toggle('registration_enabled', 'Open sign-up', 'chatvia.registration_enabled', default: false);

    expect($field->cast($saved))->toBe($expected);
})->with([
    'saved "1"' => ['1', true],
    'saved "0"' => ['0', false],
    'saved "true"' => ['true', true],
    'saved "false"' => ['false', false],
    'a JSON false' => [false, false],
    'blank' => ['', null],
    'nothing' => [null, null],
    'not a switch' => ['maybe', null],
]);

it('casts a number within its range', function (mixed $saved, ?int $expected): void {
    $field = SettingField::integer('percent', 'Percent', 'chatvia.example.percent', default: 0, min: 0, max: 100);

    expect($field->cast($saved))->toBe($expected);
})->with([
    'saved "20"' => ['20', 20],
    'the lowest' => ['0', 0],
    'the highest' => ['100', 100],
    'above the range' => ['101', null],
    'below the range' => ['-1', null],
    'a fraction' => ['2.5', null],
    'not a number' => ['many', null],
]);

it('keeps text as saved', function (): void {
    expect(SettingField::text('client_id', 'Client ID', 'services.google.client_id')->cast('abc-123'))->toBe('abc-123');
});

it('saves panel input as strings, and an off toggle as "0"', function (): void {
    $toggle = SettingField::toggle('registration_enabled', 'Open sign-up', 'chatvia.registration_enabled', default: true);
    $number = SettingField::integer('retention_days', 'Days', 'chatvia.retention.conversations_days', default: 366, min: 0);
    $text = SettingField::text('client_id', 'Client ID', 'services.google.client_id');

    expect($toggle->toPayload(true))->toBe('1')
        ->and($toggle->toPayload(false))->toBe('0')
        ->and($toggle->toPayload(null))->toBeNull()
        ->and($number->toPayload(14))->toBe('14')
        ->and($number->toPayload(0))->toBe('0')
        ->and($number->toPayload(''))->toBeNull()
        ->and($text->toPayload('  id  '))->toBe('id')
        ->and($text->toPayload(''))->toBeNull();
});

it('computes a URL default when nothing is saved', function (): void {
    $field = SettingField::url('redirect', 'Redirect URL', 'services.google.redirect', '/auth/google/callback');

    expect($field->defaultValue())->toBe(url('/auth/google/callback'));
});
